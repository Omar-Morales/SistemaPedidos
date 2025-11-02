import axios from 'axios';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

document.addEventListener('DOMContentLoaded', () => {
  // ✅ Eliminar sesión individual
document.querySelectorAll('.btn-delete-session').forEach(button => {
  button.addEventListener('click', () => {
    const sessionId = button.dataset.id;

    axios.delete(`/profile/session/${sessionId}`, {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    .then(() => {
      // Elimina el item visualmente
      const item = document.querySelector(`li[data-id="${sessionId}"]`);
      if (item) item.remove();

      // Verifica si solo queda una sesión
      const listItems = document.querySelectorAll('#sessionList li');
      const remaining = Array.from(listItems).filter(li => !li.querySelector('.badge.bg-success'));
      if (remaining.length === 0) {
        const modalButton = document.getElementById('btn-show-modal');
        if (modalButton) modalButton.remove(); // o ocultar con style.display
      }

      Toastify({
        text: "Sesión eliminada",
        gravity: "top",
        position: "right",
        backgroundColor: "#28a745",
        duration: 3000
      }).showToast();
    })
    .catch(() => {
      Toastify({
        text: "Error al eliminar la sesión",
        gravity: "top",
        position: "right",
        backgroundColor: "#dc3545",
        duration: 3000
      }).showToast();
    });
  });
});


// ✅ Eliminar todas las sesiones excepto la actual
document.getElementById('btn-destroy-all-sessions').addEventListener('click', () => {
  const password = document.getElementById('password-confirmations').value;

  axios.post('/profile/sessions/destroy', { password }, {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
  })
  .then(() => {
    // Solo dejar la sesión actual en la lista
    document.querySelectorAll('#sessionList li').forEach(li => {
      const isCurrent = li.querySelector('.badge.bg-success');
      if (!isCurrent) li.remove();
    });

    // Ocultar el botón del modal si ya no hay más de una sesión
    const listItems = document.querySelectorAll('#sessionList li');
    const remaining = Array.from(listItems).filter(li => !li.querySelector('.badge.bg-success'));
    if (remaining.length === 0) {
      const modalButton = document.getElementById('btn-show-modal');
      if (modalButton) modalButton.remove(); // o modalButton.style.display = 'none';
    }

    // Cierra modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmLogoutModal'));
    modal.hide();

    Toastify({
      text: "Sesiones cerradas correctamente",
      gravity: "top",
      position: "right",
      backgroundColor: "#28a745",
      duration: 3000
    }).showToast();
  })
  .catch(() => {
    Toastify({
      text: "Contraseña incorrecta",
      gravity: "top",
      position: "right",
      backgroundColor: "#dc3545",
      duration: 3000
    }).showToast();
  });
});


});

    document.getElementById('profileSubmit').addEventListener('click', () => {
    const form = document.getElementById('profileForm');
    const data = new FormData(form);
    data.append('_method', 'PATCH'); // Simulamos PATCH ya que axios hace POST

    axios.post('/profile', data)
        .then(res => {
        const u = res.data.user;

        // Actualiza campos visibles del perfil lateral
        document.querySelector('input[placeholder="Correo"]').value = u.email;
        document.querySelector('input[placeholder="Teléfono"]').value = u.phone || '';

        // Actualiza progress bar desde backend
        const percent = res.data.percent ?? 0;
        const bar = document.querySelector('.progress-bar');
        if (bar) {
            bar.style.width = percent + '%';
            const label = bar.querySelector('.label');
            if (label) label.textContent = percent + '%';
        }

        // Toast
        Toastify({
            text: "Perfil actualizado",
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: "#28a745",
        }).showToast();
        })
        .catch(err => {
        const msg = err.response?.data?.errors
            ? Object.values(err.response.data.errors).flat().join(', ')
            : 'Error al actualizar perfil';
        Toastify({
            text: msg,
            gravity: "top",
            position: "right",
            backgroundColor: "#dc3545",
            duration: 3000
        }).showToast();
        });
    });

    // Enviar la solicitud con Axios cuando se hace clic en el botón de actualizar
    document.getElementById('passwordSubmit').addEventListener('click', (event) => {
        event.preventDefault();  // Evitar que el formulario se envíe de forma tradicional

        const form = document.getElementById('passwordForm');
        const formData = new FormData(form);
        formData.append('_method', 'PUT'); // Simular PUT si usas POST

        // Solicitud Axios para actualizar la contraseña
        axios.post('/profile/password', formData)
            .then(response => {
                // Redirigir después de la actualización exitosa
                window.location.href = '/'; // Asegúrate de que la redirección sea a /login o cualquier otra URL
            })
            .catch(error => {
                const errors = error.response?.data?.errors;

                if (errors) {
                    // Limpiar errores previos
                    Toastify({
                        text: "Errores al actualizar la contraseña:",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#dc3545",
                    }).showToast();

                    // Iterar sobre los errores y mostrarlos con Toastify
                    Object.keys(errors).forEach(field => {
                        const errorMessages = errors[field].join(', '); // Unir mensajes
                        Toastify({
                            text: `${field.charAt(0).toUpperCase() + field.slice(1)}: ${errorMessages}`,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#dc3545",
                        }).showToast();
                    });
                } else {
                    // Si no hay errores, mostrar un mensaje genérico de error
                    Toastify({
                        text: 'Error al actualizar la contraseña.',
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#dc3545",
                        duration: 3000
                    }).showToast();
                }
            });
    });


   document.getElementById('profile-img-file-input').addEventListener('change', function () {
  const file = this.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('photo', file);

  axios.post('/profile/photo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
  .then(res => {
    // Actualiza imagen de perfil
    const newUrl = res.data.photoUrl;
    const img = document.querySelector('.profile-user img');
    if (img && newUrl) {
      img.src = newUrl + '?t=' + new Date().getTime(); // evita caché
    }

      // ✅ Actualiza imagen del header
    const headerImg = document.querySelector('.header-profile-user');
    if (headerImg && newUrl) {
        headerImg.src = newUrl + '?t=' + new Date().getTime();
    }

    // Actualiza progress bar
    const percent = res.data.percent ?? 0;
    const bar = document.querySelector('.progress-bar');
    if (bar) {
      bar.style.width = percent + '%';
      const label = bar.querySelector('.label');
      if (label) label.textContent = percent + '%';
    }

    // Toast éxito
    Toastify({
      text: "Foto de perfil actualizada",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#28a745",
    }).showToast();
  })
  .catch(err => {
    const msg = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(', ')
      : 'Error al subir la foto';
    Toastify({
      text: msg,
      gravity: "top",
      position: "right",
      backgroundColor: "#dc3545",
      duration: 3000
    }).showToast();
  });
});
