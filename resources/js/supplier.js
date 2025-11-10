import axios from "axios";

axios.defaults.headers.common["X-CSRF-TOKEN"] = document
  .querySelector('meta[name="csrf-token"]')
  .getAttribute("content");

const modal = new bootstrap.Modal(document.getElementById("modalProveedor"));
const suppliersTable = $("#suppliersTable").DataTable({
  processing: true,
  serverSide: true,
  ajax: {
    url: "/suppliers/data",
    type: "GET",
  },
  columns: [
    {
      data: "row_number",
      name: "row_number",
      searchable: false,
    },
    { data: "ruc", name: "ruc" },
    { data: "name", name: "name" },
    { data: "email", name: "email" },
    { data: "phone", name: "phone" },
    { data: "acciones", name: "acciones", orderable: false, searchable: false },
  ],
  language: { url: "/assets/js/es-ES.json" },
  responsive: true,
  autoWidth: false,
  order: [[0, "desc"]],
  pageLength: 10,
  dom: "Bfrtip",
  buttons: [
    {
      extend: "colvis",
      text: "Seleccionar Columnas",
      className: "btn btn-info",
      postfixButtons: ["colvisRestore"],
    },
  ],
});

function refreshColvisMarkers() {
  $(".dt-button-collection .dt-button").each(function () {
    const isActive =
      $(this).hasClass("active") || $(this).hasClass("dt-button-active");
    const marker = $(this).find(".checkmark");
    if (isActive && marker.length === 0) {
      $(this).prepend('<span class="checkmark">&#10003;</span>');
    } else if (!isActive && marker.length) {
      marker.remove();
    }
  });
}

suppliersTable.on("buttons-action", () => setTimeout(refreshColvisMarkers, 10));
$(document).on("click", ".buttons-colvis", () => setTimeout(refreshColvisMarkers, 50));
$(document).ready(() => setTimeout(refreshColvisMarkers, 100));

$("#btnCrearProveedor").on("click", () => {
  $("#modalProveedorLabel").text("Nuevo Proveedor");
  $("#formProveedor")[0].reset();
  $("#proveedor_id").val("");
  modal.show();
});

$(document).on("click", ".edit-btn", async function () {
  const id = $(this).data("id");
  try {
    const { data } = await axios.get(`/suppliers/${id}`);
    $("#modalProveedorLabel").text("Editar Proveedor");
    $("#proveedor_id").val(data.id);
    $("#ruc").val(data.ruc);
    $("#name").val(data.name);
    $("#email").val(data.email);
    $("#phone").val(data.phone);
    modal.show();
  } catch (error) {
    console.error(error);
    Toastify({
      text: "No se pudo cargar el proveedor",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#dc3545",
    }).showToast();
  }
});

document.getElementById("formProveedor").addEventListener("submit", function (e) {
  e.preventDefault();
  const id = document.getElementById("proveedor_id").value;
  const url = id ? `/suppliers/${id}` : "/suppliers";
  const formData = new FormData(this);
  if (id) formData.append("_method", "PUT");

  axios
    .post(url, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    })
    .then(({ data }) => {
      modal.hide();
      this.reset();
      suppliersTable.ajax.reload(null, false);
      Toastify({
        text: data.message ?? "Accion realizada",
        duration: 3000,
        gravity: "top",
        position: "right",
        backgroundColor: "#28a745",
      }).showToast();
    })
    .catch((error) => {
      console.error(error);
      Toastify({
        text:
          error.response?.data?.message ?? "Error al guardar el proveedor",
        duration: 3000,
        gravity: "top",
        position: "right",
        backgroundColor: "#dc3545",
      }).showToast();
    });
});

$(document).on("click", ".delete-btn", function (e) {
  e.preventDefault();
  const id = $(this).data("id");

  Swal.fire({
    title: "Estas seguro?",
    text: "No podras revertir esta accion.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Si, eliminar",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (!result.isConfirmed) return;

    axios
      .delete(`/suppliers/${id}`)
      .then(({ data }) => {
        suppliersTable.ajax.reload(null, false);
        Toastify({
          text: data.message ?? "Proveedor eliminado",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#28a745",
        }).showToast();
      })
      .catch((error) => {
        console.error(error);
        Toastify({
          text: "Error al eliminar el proveedor",
          duration: 3000,
          gravity: "top",
          position: "right",
          backgroundColor: "#dc3545",
        }).showToast();
      });
  });
});

document.getElementById("ruc").addEventListener("input", function () {
  this.value = this.value.replace(/[^0-9]/g, "");
});

document.getElementById("phone").addEventListener("input", function () {
  this.value = this.value.replace(/[^0-9]/g, "");
});
