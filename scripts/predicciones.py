#!/usr/bin/env python3
"""
Módulo de predicciones para el dashboard de ventas.

- Se conecta a PostgreSQL.
- Entrena dos modelos:
    1) Prophet -> predice ingresos diarios.
    2) RandomForest -> predice unidades vendidas por producto.

- Genera:
    a) Tabla de predicciones de ingresos diarios.
    b) Tabla de predicciones por producto y fecha.
    c) Tabla de TOP productos por mes (en unidades).
"""

import logging
import os
from datetime import timedelta

import numpy as np
import pandas as pd
from prophet import Prophet
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
from sqlalchemy import create_engine, text, inspect
from sqlalchemy.engine import Engine
from sqlalchemy.exc import OperationalError
from dotenv import load_dotenv

# Configuración básica de logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)

load_dotenv()

# Constantes
VENTAS_TABLE = os.getenv("VENTAS_TABLE", "ventas")
DETALLE_VENTAS_TABLE = os.getenv("DETALLE_VENTAS_TABLE", "detalle_ventas")
PRODUCTS_TABLE = os.getenv("PRODUCTS_TABLE", "products")
PRED_REVENUE_TABLE = os.getenv("PRED_REVENUE_TABLE", "predicciones_ingresos")
PRED_PRODUCTS_TABLE = os.getenv("PRED_PRODUCTS_TABLE", "predicciones_productos")
TOP_PRODUCTS_TABLE = os.getenv("TOP_PRODUCTS_TABLE", "top_productos_predichos")
FUTURE_DAYS = int(os.getenv("FORECAST_DAYS", "30"))
EVAL_REVENUE_TABLE = os.getenv("EVAL_REVENUE_TABLE", "evaluacion_predicciones_ingresos")


def get_engine() -> Engine:
    """
    Crea y retorna un engine de SQLAlchemy usando variables de entorno.
    """
    db_host = os.getenv("DB_HOST") or os.getenv("PGHOST") or os.getenv("DB_HOSTNAME")
    db_port = os.getenv("DB_PORT", os.getenv("PGPORT", "5432"))
    db_name = os.getenv("DB_NAME") or os.getenv("DB_DATABASE")
    db_user = os.getenv("DB_USER") or os.getenv("DB_USERNAME")
    db_password = os.getenv("DB_PASSWORD") or os.getenv("PGPASSWORD")

    missing = [k for k, val in {
        "DB_HOST/PGHOST": db_host,
        "DB_NAME/DB_DATABASE": db_name,
        "DB_USER/DB_USERNAME": db_user,
        "DB_PASSWORD": db_password,
    }.items() if not val]
    if missing:
        raise EnvironmentError(f"Faltan variables de entorno requeridas: {', '.join(missing)}")

    url = f"postgresql+psycopg2://{db_user}:{db_password}@{db_host}:{db_port}/{db_name}"
    try:
        engine = create_engine(url)
        # Verificar conexión
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        logging.info("Conexión a PostgreSQL exitosa.")
        return engine
    except OperationalError as exc:
        logging.error("Error al conectar con PostgreSQL: %s", exc)
        raise


def load_sales_data(engine: Engine) -> pd.DataFrame:
    """
    Carga los datos de la tabla de ventas y realiza limpieza básica.
    """
    query = f"""
        SELECT
            v.sale_date AS fecha,
            COALESCE(p.name, 'SIN PRODUCTO') AS producto,
            dv.quantity AS cantidad,
            COALESCE(dv.warehouse, v.warehouse) AS almacen,
            dv.subtotal AS total
        FROM {DETALLE_VENTAS_TABLE} dv
        JOIN {VENTAS_TABLE} v ON v.id = dv.sale_id
        LEFT JOIN {PRODUCTS_TABLE} p ON p.id = dv.product_id
    """
    df = pd.read_sql(query, engine, parse_dates=["fecha"])
    logging.info("Ventas cargadas: %d registros.", len(df))

    # Validar columnas críticas
    required_cols = ["fecha", "producto", "cantidad", "almacen", "total"]
    df = df.dropna(subset=required_cols)
    df = df[df["cantidad"] >= 0]
    df = df[df["total"] >= 0]

    if df.empty:
        raise ValueError("No hay datos válidos para entrenar los modelos.")

    return df


def evaluate_revenue_predictions(engine: Engine) -> dict:
    """
    Compara los ingresos reales contra las predicciones existentes
    y guarda el error por fecha. Devuelve métricas agregadas (MAE, RMSE, MAPE)
    para lo recién evaluado.
    """
    inspector = inspect(engine)
    if not inspector.has_table(PRED_REVENUE_TABLE):
        logging.info("No existe la tabla de predicciones (%s); se omite evaluación.", PRED_REVENUE_TABLE)
        return {}

    try:
        actuals = pd.read_sql(
            """
            SELECT
                DATE(sale_date) AS fecha,
                SUM(total_price) AS ingreso_real
            FROM ventas
            WHERE sale_date IS NOT NULL
              AND (status IS NULL OR status != 'cancelled')
            GROUP BY 1
            """,
            engine,
            parse_dates=["fecha"],
        )
    except Exception as exc:
        logging.warning("No se pudieron obtener ventas reales para evaluación: %s", exc)
        return {}

    if actuals.empty:
        logging.info("No hay ventas reales para evaluar predicciones.")
        return {}

    preds = pd.read_sql(
        f"SELECT fecha, ingreso_predicho FROM {PRED_REVENUE_TABLE}",
        engine,
        parse_dates=["fecha"],
    )

    merged = actuals.merge(preds, on="fecha", how="inner")
    if merged.empty:
        logging.info("No hay coincidencias entre predicciones y valores reales para evaluar.")
        return {}

    if inspector.has_table(EVAL_REVENUE_TABLE):
        existentes = pd.read_sql(
            f"SELECT fecha FROM {EVAL_REVENUE_TABLE}",
            engine,
            parse_dates=["fecha"],
        )
        if not existentes.empty:
            merged = merged[~merged["fecha"].isin(existentes["fecha"])]

    if merged.empty:
        logging.info("Todas las fechas reales ya fueron evaluadas previamente.")
        return {}

    merged["error"] = merged["ingreso_real"] - merged["ingreso_predicho"]
    merged["error_absoluto"] = merged["error"].abs()
    merged["error_cuadratico"] = merged["error"] ** 2
    merged["error_porcentual"] = merged.apply(
        lambda row: (row["error_absoluto"] / row["ingreso_real"]) * 100 if row["ingreso_real"] else None,
        axis=1,
    )

    df_evaluacion = merged[["fecha", "ingreso_predicho", "ingreso_real", "error_absoluto", "error_cuadratico", "error_porcentual"]]
    df_evaluacion["fecha"] = df_evaluacion["fecha"].dt.date
    df_evaluacion.to_sql(EVAL_REVENUE_TABLE, engine, if_exists="append", index=False)
    logging.info("Evaluación guardada para %d fechas en %s.", len(df_evaluacion), EVAL_REVENUE_TABLE)

    mae = df_evaluacion["error_absoluto"].mean()
    rmse = np.sqrt(df_evaluacion["error_cuadratico"].mean())
    mape = df_evaluacion["error_porcentual"].dropna().mean()
    return {
        "evaluaciones_registradas": len(df_evaluacion),
        "mae": mae,
        "rmse": rmse,
        "mape": mape,
    }


def train_and_forecast_revenue(df_ventas: pd.DataFrame, dias_a_predecir: int = FUTURE_DAYS) -> pd.DataFrame:
    """
    Agrupa por fecha y entrena un modelo Prophet para predecir ingresos diarios.
    """
    df_diario = (
        df_ventas.groupby("fecha", as_index=False)["total"]
        .sum()
        .rename(columns={"fecha": "ds", "total": "y"})
        .sort_values("ds")
    )

    if len(df_diario) < 10:
        raise ValueError("Se necesitan al menos 10 días de datos para entrenar Prophet.")

    model = Prophet(
        daily_seasonality=True,
        weekly_seasonality=True,
        yearly_seasonality=True,
    )
    model.fit(df_diario)

    future = model.make_future_dataframe(periods=dias_a_predecir)
    forecast = model.predict(future)

    df_forecast = forecast[["ds", "yhat", "yhat_lower", "yhat_upper"]].copy()
    df_forecast.rename(
        columns={
            "yhat": "ingreso_predicho",
            "yhat_lower": "ingreso_predicho_min",
            "yhat_upper": "ingreso_predicho_max",
        },
        inplace=True,
    )

    logging.info("Predicciones de ingresos generadas para %d días.", dias_a_predecir)
    return df_forecast


def build_features_for_products(df: pd.DataFrame) -> pd.DataFrame:
    """
    Crea dataset agregado por producto y fecha y añade features temporales.
    """
    df = df.copy()
    df["fecha"] = pd.to_datetime(df["fecha"])
    agg = (
        df.groupby(["fecha", "producto"], as_index=False)["cantidad"]
        .sum()
        .rename(columns={"cantidad": "cantidad_total_dia"})
        .sort_values(["producto", "fecha"])
    )

    agg["anio"] = agg["fecha"].dt.year
    agg["mes"] = agg["fecha"].dt.month
    agg["dia"] = agg["fecha"].dt.day
    agg["dia_semana"] = agg["fecha"].dt.weekday
    return agg


def train_and_forecast_product_sales(
    df_ventas: pd.DataFrame,
    dias_a_predecir: int = FUTURE_DAYS,
) -> pd.DataFrame:
    """
    Entrena un RandomForest para predecir cantidades por producto y fecha futura.
    """
    df_products = build_features_for_products(df_ventas)
    if df_products.empty:
        raise ValueError("No hay datos agregados por producto para entrenar el modelo.")

    label_encoder = LabelEncoder()
    df_products["producto_encoded"] = label_encoder.fit_transform(df_products["producto"])

    feature_cols = ["anio", "mes", "dia", "dia_semana", "producto_encoded"]
    target_col = "cantidad_total_dia"

    model = RandomForestRegressor(
        n_estimators=200,
        random_state=42,
        n_jobs=-1,
    )
    model.fit(df_products[feature_cols], df_products[target_col])

    # Crear fechas futuras
    last_date = df_products["fecha"].max()
    future_dates = pd.date_range(last_date + timedelta(days=1), periods=dias_a_predecir, freq="D")

    future_rows = []
    productos_unicos = df_products["producto"].unique()
    for fecha in future_dates:
        for producto in productos_unicos:
            future_rows.append({"fecha": fecha, "producto": producto})

    df_future = pd.DataFrame(future_rows)
    df_future["anio"] = df_future["fecha"].dt.year
    df_future["mes"] = df_future["fecha"].dt.month
    df_future["dia"] = df_future["fecha"].dt.day
    df_future["dia_semana"] = df_future["fecha"].dt.weekday
    df_future["producto_encoded"] = label_encoder.transform(df_future["producto"])

    df_future["cantidad_predicha"] = model.predict(df_future[feature_cols])
    df_future["cantidad_predicha"] = df_future["cantidad_predicha"].clip(lower=0).round(2)

    logging.info("Predicciones de productos generadas para %d días.", dias_a_predecir)
    return df_future


def summarize_top_products_monthly(df_pred_products: pd.DataFrame, top_n: int = 10) -> pd.DataFrame:
    """
    Resume las predicciones por producto y mes para obtener los TOP N mensuales.
    """
    df = df_pred_products.copy()
    df["fecha"] = pd.to_datetime(df["fecha"])
    df["anio"] = df["fecha"].dt.year
    df["mes"] = df["fecha"].dt.month

    df_summary = (
        df.groupby(["anio", "mes", "producto"], as_index=False)["cantidad_predicha"]
        .sum()
        .rename(columns={"cantidad_predicha": "cantidad_predicha_mes"})
    )

    df_summary = df_summary.sort_values(
        ["anio", "mes", "cantidad_predicha_mes"],
        ascending=[True, True, False],
    )

    if top_n:
        df_summary = (
            df_summary
            .groupby(["anio", "mes"], group_keys=False)
            .head(top_n)
        )

    return df_summary


def save_revenue_predictions(engine: Engine, df_pred_revenue: pd.DataFrame) -> None:
    """
    Guarda la tabla de predicciones de ingresos.
    """
    df_to_save = df_pred_revenue.copy()
    df_to_save["fecha"] = df_to_save["ds"].dt.date
    df_to_save = df_to_save[["fecha", "ingreso_predicho", "ingreso_predicho_min", "ingreso_predicho_max"]]

    df_to_save.to_sql(PRED_REVENUE_TABLE, engine, if_exists="replace", index=False)
    logging.info("Tabla %s actualizada con %d registros.", PRED_REVENUE_TABLE, len(df_to_save))


def save_product_predictions(
    engine: Engine,
    df_pred_products: pd.DataFrame,
    df_top_products: pd.DataFrame,
) -> None:
    """
    Guarda las tablas de predicciones por producto y el ranking TOP N mensual.
    """
    df_pred_products_to_save = df_pred_products.copy()
    df_pred_products_to_save["fecha"] = df_pred_products_to_save["fecha"].dt.date
    df_pred_products_to_save = df_pred_products_to_save[["fecha", "producto", "cantidad_predicha"]]
    df_pred_products_to_save.to_sql(PRED_PRODUCTS_TABLE, engine, if_exists="replace", index=False)
    logging.info("Tabla %s actualizada con %d registros.", PRED_PRODUCTS_TABLE, len(df_pred_products_to_save))

    df_top_products.to_sql(TOP_PRODUCTS_TABLE, engine, if_exists="replace", index=False)
    logging.info("Tabla %s actualizada con %d registros.", TOP_PRODUCTS_TABLE, len(df_top_products))


def run_all_predictions(future_days: int | None = None, top_n: int | None = None) -> dict:
    """
    Ejecuta todo el flujo de predicciones y guarda en BD.
    Devuelve un pequeño resumen para usar en la API Flask.
    """
    dias_a_predecir = future_days or FUTURE_DAYS
    top_n = top_n or int(os.getenv("TOP_PRODUCTOS", "10"))

    engine = get_engine()
    evaluacion = evaluate_revenue_predictions(engine)
    df_ventas = load_sales_data(engine)

    # Modelo 1: ingresos
    df_pred_revenue = train_and_forecast_revenue(df_ventas, dias_a_predecir=dias_a_predecir)
    save_revenue_predictions(engine, df_pred_revenue)

    # Modelo 2: productos
    df_pred_products = train_and_forecast_product_sales(df_ventas, dias_a_predecir=dias_a_predecir)
    df_top_products = summarize_top_products_monthly(df_pred_products, top_n=top_n)
    save_product_predictions(engine, df_pred_products, df_top_products)

    # Pequeño resumen para la API
    resumen = {
        "dias_a_predecir": dias_a_predecir,
        "top_n": top_n,
        "registros_pred_ingresos": len(df_pred_revenue),
        "registros_pred_productos": len(df_pred_products),
        "registros_top_productos": len(df_top_products),
    }
    if evaluacion:
        resumen["evaluacion_ingresos"] = evaluacion
    return resumen
