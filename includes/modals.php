<?php

function modal_confirm_delete_products()
{
    echo '<div id="delete-confirm-modal" style="
        display: none; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100vw; 
        height: 100vh; 
        background: rgba(0,0,0,0.5); 
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;">
        <div style="
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            text-align: center; 
            max-width: 400px; 
            width: 90%;
            margin: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <h2 style="margin-top: 0; color: #23282d;">Advertencia</h2>
            <p style="margin: 15px 0;">Esta acción eliminará todos los productos de New Bytes.</p>
            <form id="confirm-delete-form">
                <input type="hidden" name="action" value="nb_delete_products" />
                <input type="hidden" name="delete_all" value="1" />';
    wp_nonce_field('nb_delete_all', 'nb_delete_all_nonce');
    echo '      <div style="margin-top: 20px;">
                    <button type="button" id="confirm-delete-btn" class="button" style="
                        background-color: #f55a39;
                        min-width: 130px;
                        height: 40px;
                        color: #fff;
                        border: none;
                        padding: 5px 20px;
                        font-weight: bold;
                        border-radius: 5px;
                        cursor: pointer;
                        margin-right: 10px;
                        transition: background-color 0.2s;">
                            Eliminar
                    </button>
                    <button type="button" id="cancel-delete" class="button"
                        style="
                        min-width: 130px;
                        height: 40px;
                        background-color: #e0e0e0;
                        color: #333;
                        border: none;
                        padding: 5px 20px;
                        font-weight: bold;
                        border-radius: 5px;
                        cursor: pointer;
                        transition: background-color 0.2s;">
                            Cancelar
                    </button>
                </div>
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

function modal_confirm_update_()
{
    echo '<div id="update-description-confirm-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 9999;">
        <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 400px; width: 100%;">
            <h2>Advertencia</h2>
            <p><strong>Esta acción reemplazará todas las descripciones de los productos pertenecientes a NewBytes. Ten en cuenta que se sobrescribirán todas las descripciones actuales.</strong></p>
            <form id="confirm-update-description-form" style="display: inline;">
                <input type="hidden" name="action" value="nb_update_description_products" />
                <input type="hidden" name="update_description_all" value="1" />';
    wp_nonce_field('nb_update_description_all', 'nb_update_description_all_nonce');
    echo '  <button type="button" id="confirm-update-description-btn" class="button" style="background-color: #5e41de33; min-width: 130px; height: 40px; color: #52469d; border: none; padding: 5px 10px; font-weight: bold; border-radius: 5px; cursor: pointer;">
                        Actualizar Descripciones
                    </button>
                    <button type="button" id="cancel-update-description" class="button" style="min-width: 130px; height: 40px; background-color: #e0e0e0; color: #333; border: none; padding: 5px 10px; font-weight: bold; border-radius: 5px; cursor: pointer;">
                        Cerrar
                    </button>
                </form>
            </div>
        </div>';
}
function modal_success_confirm_update()
{
    echo '<div id="success-confirm-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 9999;">
        <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 400px; width: 100%;">
            <h2>Descripciones sincronizadas con éxito</h2>
            <button type="button" id="close-success-modal-btn" class="button" style="min-width: 130px; height: 40px; background-color: #4CAF50; color: #fff; border: none; padding: 5px 10px; font-weight: bold; border-radius: 5px; cursor: pointer;">
                Cerrar
            </button>
        </div>
    </div>';
}
function modal_fail_confirm_update()
{
    echo '<div id="fail-confirm-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 9999;">
        <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 400px; width: 100%;">
            <h2>Error</h2>
            <p>Hubo un problema al sincronizar las descripciones. Por favor, inténtalo de nuevo.</p>
            <button type="button" id="close-fail-modal-btn" class="button" style="min-width: 130px; height: 40px; background-color: #f44336; color: #fff; border: none; padding: 5px 10px; font-weight: bold; border-radius: 5px; cursor: pointer;">
                Cerrar
            </button>
        </div>
    </div>';
}

function js_handler_modals()
{
    echo '<script>
    console.log("Script iniciado");
    document.addEventListener("DOMContentLoaded", function() {
        console.log("DOM Cargado");
        
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

        console.log("Elementos encontrados:", {
            deleteAllBtn: deleteAllBtn,
            deleteConfirmModal: deleteConfirmModal,
            cancelDeleteBtn: cancelDeleteBtn,
            confirmDeleteBtn: confirmDeleteBtn,
            confirmDeleteForm: confirmDeleteForm
        });

        if (deleteAllBtn && deleteConfirmModal && cancelDeleteBtn && confirmDeleteBtn) {
            console.log("Todos los elementos necesarios están presentes");
            
            deleteAllBtn.addEventListener("click", function() {
                console.log("Botón eliminar clickeado");
                deleteConfirmModal.style.display = "flex";
            });

            cancelDeleteBtn.addEventListener("click", function() {
                console.log("Botón cancelar clickeado");
                deleteConfirmModal.style.display = "none";
            });

            deleteConfirmModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    console.log("Click fuera del modal");
                    deleteConfirmModal.style.display = "none";
                }
            });

            confirmDeleteBtn.addEventListener("click", function() {
                console.log("Botón confirmar clickeado");
                var formData = new FormData(confirmDeleteForm);
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        // Ocultar el modal de confirmación
                        deleteConfirmModal.style.display = "none";
                        
                        // Mostrar mensaje de éxito
                        const successMessage = document.createElement("div");
                        successMessage.className = "notice notice-success is-dismissible";
                        successMessage.style.margin = "20px 0";
                        successMessage.style.padding = "12px";
                        successMessage.innerHTML = `
                            <h3 style="margin: 0 0 10px; color: #2c3338;">
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 24px; width: 24px; height: 24px; margin-right: 10px;"></span>
                                Productos eliminados exitosamente
                            </h3>
                            <p>Se han eliminado correctamente todos los productos de NewBytes.</p>
                        `;
                        
                        // Insertar el mensaje después del botón de eliminar
                        const deleteButton = document.getElementById("delete-all-btn");
                        if (deleteButton && deleteButton.parentNode) {
                            deleteButton.parentNode.insertBefore(successMessage, deleteButton.nextSibling);
                            
                            // Remover el mensaje después de 5 segundos
                            setTimeout(() => {
                                successMessage.remove();
                            }, 5000);
                        }
                    } else {
                        // Ocultar el modal de confirmación
                        deleteConfirmModal.style.display = "none";
                        
                        // Mostrar mensaje de error
                        const errorMessage = document.createElement("div");
                        errorMessage.className = "notice notice-error is-dismissible";
                        errorMessage.style.margin = "20px 0";
                        errorMessage.style.padding = "12px";
                        errorMessage.innerHTML = `
                            <h3 style="margin: 0 0 10px; color: #2c3338;">
                                <span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 24px; width: 24px; height: 24px; margin-right: 10px;"></span>
                                Error al eliminar productos
                            </h3>
                            <p>\${data.data || "Error desconocido al eliminar los productos."}</p>
                        `;
                        
                        // Insertar el mensaje de error después del botón de eliminar
                        const deleteButton = document.getElementById("delete-all-btn");
                        if (deleteButton && deleteButton.parentNode) {
                            deleteButton.parentNode.insertBefore(errorMessage, deleteButton.nextSibling);
                        }
                    }
                }).catch(error => {
                    console.error("Error:", error);
                    // Ocultar el modal de confirmación
                    deleteConfirmModal.style.display = "none";
                    
                    // Mostrar mensaje de error
                    alert("Error al procesar la solicitud. Por favor, inténtalo de nuevo.");
                });
            });
        }

        // Manejo del modal para "Sincronizar Descripciones"
        var updateDescriptionBtn = document.getElementById("update-description-btn");
        var updateDescriptionModal = document.getElementById("update-description-confirm-modal");
        var cancelUpdateDescriptionBtn = document.getElementById("cancel-update-description");
        var confirmUpdateDescriptionBtn = document.getElementById("confirm-update-description-btn");
        var closeSuccessModalBtn = document.getElementById("close-success-modal-btn");
        var closeFailModalBtn = document.getElementById("close-fail-modal-btn");
        var confirmUpdateDescriptionForm = document.getElementById("confirm-update-description-form");
        var successConfirmModal = document.getElementById("success-confirm-modal");
        var failConfirmModal = document.getElementById("fail-confirm-modal");

        if (updateDescriptionBtn && updateDescriptionModal && cancelUpdateDescriptionBtn && confirmUpdateDescriptionBtn) {
            updateDescriptionBtn.addEventListener("click", function() {
                updateDescriptionModal.style.display = "flex";
            });

            cancelUpdateDescriptionBtn.addEventListener("click", function() {
                updateDescriptionModal.style.display = "none";
            });

            closeSuccessModalBtn.addEventListener("click", function() {
                successConfirmModal.style.display = "none";
            });

            closeFailModalBtn.addEventListener("click", function() {
                failConfirmModal.style.display = "none";
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
                        // Mostrar estadísticas en el modal de éxito
                        const stats = data.data.stats;
                        const successMessage = `Se actualizaron ${stats.updated} productos, se crearon ${stats.created} productos nuevos y se eliminaron ${stats.deleted} productos.`;
                        
                        const successModalContent = document.querySelector("#success-confirm-modal div");
                        if (successModalContent) {
                            successModalContent.innerHTML = `
                                <h2>Sincronización completada con éxito</h2>
                                <p>${successMessage}</p>
                                <button type="button" id="close-success-modal-btn" class="button" style="min-width: 130px; height: 40px; background-color: #4CAF50; color: #fff; border: none; padding: 5px 10px; font-weight: bold; border-radius: 5px; cursor: pointer;">
                                    Cerrar
                                </button>
                            `;
                        }
                        
                        successConfirmModal.style.display = "flex";
                        updateDescriptionModal.style.display = "none";
                        
                        // Actualizar la fecha de última actualización en la interfaz
                        const lastUpdateElement = document.getElementById("last_update");
                        if (lastUpdateElement) {
                            const now = new Date();
                            const formattedDate = now.getDate().toString().padStart(2, \'0\') + \'/\' +
                                                (now.getMonth() + 1).toString().padStart(2, \'0\') + \'/\' +
                                                now.getFullYear() + \' \' +
                                                now.getHours().toString().padStart(2, \'0\') + \':\' +
                                                now.getMinutes().toString().padStart(2, \'0\');
                            lastUpdateElement.textContent = formattedDate;
                        }

                        // Volver a agregar el event listener para el botón de cerrar
                        const newCloseBtn = document.getElementById("close-success-modal-btn");
                        if (newCloseBtn) {
                            newCloseBtn.addEventListener("click", function() {
                                successConfirmModal.style.display = "none";
                            });
                        }
                    } else {
                        failConfirmModal.style.display = "flex";
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
