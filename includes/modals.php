<?php

function modal_confirm_delete_products()
{
    echo '<div id="delete-confirm-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 9999;">
        <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 400px; width: 100%;">
            <h2>Advertencia</h2>
            <p>Esta acción eliminará todos los productos de NewBytes.</p>
            <form id="confirm-delete-form" style="display: inline;">
                <input type="hidden" name="action" value="nb_delete_products" />
                <input type="hidden" name="delete_all" value="1" />';
    wp_nonce_field('nb_delete_all', 'nb_delete_all_nonce');
    echo '  <button type="button" id="confirm-delete-btn" class="button" style="
                    background-color: #f55a39;
                    min-width: 130px;
                    height: 40px;
                    color: #fff;
                    border: none;
                    padding: 5px 10px;
                    font-weight: bold;
                    border-radius: 5px;
                    cursor: pointer;">
                        Eliminar
                    </button>
                    <button type="button" id="cancel-delete" class="button"
                    style="
                    min-width: 130px;
                    height: 40px;
                    background-color: #e0e0e0;
                    color: #333;
                    border: none;
                    padding: 5px 10px;
                    font-weight: bold;
                    border-radius: 5px;
                    cursor: pointer;">
                        Cancelar
                    </button>
                </form>
            </div>
        </div>';
}

function btn_delete_products()
{
    echo '<button type="button" class="button button-secondary" id="delete-all-btn" style="margin-top: 20px; border: none; background-color: #f55a39; color: #fff;">
        Eliminar Productos
    </button>';
}

function btn_update_description_products()
{
    echo '<button type="button" class="button button-secondary" id="update-description-btn" style="margin-top: 20px; margin-right:20px; border: none; background-color: #5e41de33; color: #52469d;">
        Sincronizar Descripciones
            </button>';
}

function js_handler_modals()
{
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Manejo del modal de actualización del conector NB
        var updateConnectorBtn = document.getElementById("update-connector-btn");
        var updateConnectorModal = document.getElementById("update-connector-modal");
        var closeModalBtn = document.getElementById("close-modal-btn");

        if (updateConnectorBtn && updateConnectorModal && closeModalBtn) {
            updateConnectorBtn.addEventListener("click", function() {
                updateConnectorModal.style.display = "flex";
            });

            closeModalBtn.addEventListener("click", function() {
                updateConnectorModal.style.display = "none";
            });

            updateConnectorModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    updateConnectorModal.style.display = "none";
                }
            });
        }

        // Manejo del modal de confirmación de eliminación de productos
        var deleteAllBtn = document.getElementById("delete-all-btn");
        var deleteConfirmModal = document.getElementById("delete-confirm-modal");
        var cancelDeleteBtn = document.getElementById("cancel-delete");
        var confirmDeleteBtn = document.getElementById("confirm-delete-btn");
        var confirmDeleteForm = document.getElementById("confirm-delete-form");

        if (deleteAllBtn && deleteConfirmModal && cancelDeleteBtn && confirmDeleteBtn) {
            deleteAllBtn.addEventListener("click", function() {
                deleteConfirmModal.style.display = "flex";
            });

            cancelDeleteBtn.addEventListener("click", function() {
                deleteConfirmModal.style.display = "none";
            });

            deleteConfirmModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    deleteConfirmModal.style.display = "none";
                }
            });

            confirmDeleteBtn.addEventListener("click", function() {
                var formData = new FormData(confirmDeleteForm);
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        alert("Productos eliminados exitosamente.");
                        deleteConfirmModal.style.display = "none";
                    } else {
                        alert("Error: " + data.data);
                    }
                }).catch(error => {
                    console.error("Error:", error);
                });
            });
        }

        // Manejo del modal para "Sincronizar Descripciones"
        // Manejo del modal para "Sincronizar Descripciones"
        var updateDescriptionBtn = document.getElementById("update-description-btn");
        var updateDescriptionModal = document.getElementById("update-description-confirm-modal");
        var cancelUpdateDescriptionBtn = document.getElementById("cancel-update-description");
        var confirmUpdateDescriptionBtn = document.getElementById("confirm-update-description-btn");
        var confirmUpdateDescriptionForm = document.getElementById("confirm-update-description-form");

        if (updateDescriptionBtn && updateDescriptionModal && cancelUpdateDescriptionBtn && confirmUpdateDescriptionBtn) {
            updateDescriptionBtn.addEventListener("click", function() {
                updateDescriptionModal.style.display = "flex";
            });

            cancelUpdateDescriptionBtn.addEventListener("click", function() {
                updateDescriptionModal.style.display = "none";
            });

            updateDescriptionModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    updateDescriptionModal.style.display = "none";
                }
            });

            confirmUpdateDescriptionBtn.addEventListener("click", function() {
                // Cambiar el texto del botón al spinner de FontAwesome y deshabilitarlo
                confirmUpdateDescriptionBtn.innerHTML = \'<i class="fas fa-spinner fa-spin"></i> Procesando...\';
                confirmUpdateDescriptionBtn.disabled = true;

                var formData = new FormData(confirmUpdateDescriptionForm);
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        alert("Descripciones sincronizadas exitosamente.");
                        updateDescriptionModal.style.display = "none";
                    } else {
                        alert("Error: " + data.data);
                    }
                }).catch(error => {
                    console.error("Error:", error);
                }).finally(() => {
                    // Restaurar el texto del botón y habilitarlo
                    confirmUpdateDescriptionBtn.innerHTML = "Actualizar Descripciones";
                    confirmUpdateDescriptionBtn.disabled = false;
                });
            });
        }
    });
    </script>';
}

function enqueue_fontawesome()
{
    wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
}

add_action('admin_enqueue_scripts', 'enqueue_fontawesome');
