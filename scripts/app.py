from flask import Flask, jsonify, request
from apscheduler.schedulers.background import BackgroundScheduler
import logging
import atexit
import os

try:
    from scripts.predicciones import run_all_predictions
except ImportError:
    from predicciones import run_all_predictions

app = Flask(__name__)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)

# Lee frecuencia desde env (por ejemplo, cada 24 horas)
PRED_INTERVAL_HOURS = int(os.getenv("PRED_INTERVAL_HOURS", "24"))
PRED_SCHEDULER_TRIGGER = os.getenv("PRED_SCHEDULER_TRIGGER", "interval")  # interval | cron
PRED_CRON_HOUR = int(os.getenv("PRED_CRON_HOUR", "0"))
PRED_CRON_MINUTE = int(os.getenv("PRED_CRON_MINUTE", "0"))

scheduler = BackgroundScheduler()


def job_predicciones():
    """
    Job que se ejecuta en segundo plano para generar las predicciones.
    """
    try:
        logging.info("Iniciando job automático de predicciones...")
        resumen = run_all_predictions()
        logging.info("Job automático completado. Resumen: %s", resumen)
    except Exception as exc:
        logging.exception("Error en job automático de predicciones: %s", exc)


def start_scheduler():
    """
    Inicializa el scheduler si no está corriendo.
    """
    if scheduler.running:
        return

    if PRED_SCHEDULER_TRIGGER == "cron":
        logging.info("Iniciando APScheduler con trigger CRON a las %02d:%02d", PRED_CRON_HOUR, PRED_CRON_MINUTE)
        scheduler.add_job(
            func=job_predicciones,
            trigger="cron",
            hour=PRED_CRON_HOUR,
            minute=PRED_CRON_MINUTE,
            id="predicciones_job",
            replace_existing=True,
        )
    else:
        logging.info("Iniciando APScheduler con intervalo de %s horas", PRED_INTERVAL_HOURS)
        scheduler.add_job(
            func=job_predicciones,
            trigger="interval",
            hours=PRED_INTERVAL_HOURS,
            id="predicciones_job",
            replace_existing=True,
        )
    scheduler.start()
    atexit.register(lambda: scheduler.shutdown())


_scheduler_initialized = False


@app.before_request
def ensure_scheduler_started():
    global _scheduler_initialized
    if not _scheduler_initialized:
        start_scheduler()
        _scheduler_initialized = True


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"}), 200


@app.route("/predicciones/run", methods=["POST"])
def run_predicciones():
    """
    Ejecuta el proceso completo de predicciones bajo demanda.
    """
    try:
        data = request.get_json(silent=True) or {}
        future_days = data.get("future_days")
        top_n = data.get("top_n")

        logging.info("Ejecutando predicciones manualmente con future_days=%s, top_n=%s", future_days, top_n)
        resumen = run_all_predictions(future_days=future_days, top_n=top_n)

        return jsonify({
            "status": "ok",
            "message": "Predicciones ejecutadas y tablas actualizadas.",
            "resumen": resumen,
        }), 200

    except Exception as exc:
        logging.exception("Error al ejecutar las predicciones manuales.")
        return jsonify({
            "status": "error",
            "message": str(exc),
        }), 500


if __name__ == "__main__":
    # Para desarrollo local
    app.run(host="0.0.0.0", port=5000, debug=True)
