<?php

function modal_confirm_delete_products()
{
    echo '<div id="delete-confirm-modal" class="nb-modal hidden">
        <div class="nb-modal-content">
            <div class="nb-modal-icon">
                <svg class="nb-icon-lg" style="color: #f59e0b;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="nb-modal-title">Advertencia</h2>
            <p class="nb-modal-text">Esta acción eliminará todos los productos de New Bytes.</p>
            <form id="confirm-delete-form">
                <input type="hidden" name="action" value="nb_delete_products" />
                <input type="hidden" name="delete_all" value="1" />';
    wp_nonce_field('nb_delete_all', 'nb_delete_all_nonce');
    echo '      <div class="nb-modal-actions">
                    <button type="button" id="confirm-delete-btn" class="nb-btn nb-btn-red">
                        <svg xmlns="http://www.w3.org/2000/svg" class="nb-icon-md nb-mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Eliminar
                    </button>
                    <button type="button" id="cancel-delete" class="nb-btn" style="background-color: white; color: #374151; border: 1px solid #d1d5db;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="nb-icon-md nb-mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>';
}

function btn_delete_products()
{
    echo '<button type="button" id="delete-all-btn" class="nb-btn nb-btn-red nb-mt-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="nb-icon-md nb-mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        Eliminar Productos
    </button>';
}

function btn_update_description_products()
{
    echo '<button type="button" id="update-description-btn" class="nb-btn nb-btn-indigo nb-mt-4 nb-mr-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="nb-icon-md nb-mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
        </svg>
        Sincronizar Descripciones
    </button>';
}

function modal_confirm_update_()
{
    echo '<div id="update-description-confirm-modal" class="nb-modal hidden">
        <div class="nb-modal-content">
            <div class="nb-modal-icon">
                <svg class="nb-icon-lg" style="color: #f59e0b;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="nb-modal-title">Advertencia</h2>
            <p class="nb-modal-text"><strong>Esta acción reemplazará todas las descripciones de los productos pertenecientes a NewBytes. Ten en cuenta que se sobrescribirán todas las descripciones actuales.</strong></p>
            <form id="confirm-update-description-form" style="display: inline-block;">
                <input type="hidden" name="action" value="nb_update_description_products" />
                <input type="hidden" name="update_description_all" value="1" />';
    wp_nonce_field('nb_update_description_all', 'nb_update_description_all_nonce');
    echo '  <div class="nb-modal-actions">
                <button type="button" id="confirm-update-description-btn" class="nb-btn nb-btn-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nb-icon-md nb-mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                    </svg>
                    Actualizar Descripciones
                </button>
                <button type="button" id="cancel-update-description" class="nb-btn" style="background-color: white; color: #374151; border: 1px solid #d1d5db;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="nb-icon-md nb-mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Cerrar
                </button>
            </div>
        </form>
    </div>
</div>';
}
function modal_success_confirm_update()
{
    echo '<div id="success-confirm-modal" class="nb-modal hidden">
        <div class="nb-modal-content">
            <div class="nb-modal-icon">
                <svg class="nb-icon-lg" style="color: #10b981;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="nb-modal-title" style="margin-bottom: 1rem;">Descripciones sincronizadas con éxito</h2>
            <button type="button" id="close-success-modal-btn" class="nb-btn" style="background-color: #10b981; color: white;">
                Cerrar
            </button>
        </div>
    </div>';
}
function modal_fail_confirm_update()
{
    echo '<div id="fail-confirm-modal" class="nb-modal hidden">
        <div class="nb-modal-content">
            <div class="nb-modal-icon">
                <svg class="nb-icon-lg" style="color: #ef4444;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="nb-modal-title">Error al sincronizar descripciones</h2>
            <p class="nb-modal-text">Hubo un problema al sincronizar las descripciones. Por favor, inténtalo de nuevo.</p>
            <button type="button" id="close-fail-modal-btn" class="nb-btn nb-btn-red">
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
                updateConnectorModal.classList.remove("hidden");
                updateConnectorModal.classList.add("flex");
            });

            closeModalBtn.addEventListener("click", function() {
                updateConnectorModal.classList.add("hidden");
                updateConnectorModal.classList.remove("flex");
            });

            updateConnectorModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    updateConnectorModal.classList.add("hidden");
                    updateConnectorModal.classList.remove("flex");
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
                deleteConfirmModal.classList.remove("hidden");
                deleteConfirmModal.classList.add("flex");
            });

            cancelDeleteBtn.addEventListener("click", function() {
                console.log("Cancelar eliminación clickeado");
                deleteConfirmModal.classList.add("hidden");
                deleteConfirmModal.classList.remove("flex");
            });

            deleteConfirmModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    console.log("Click fuera del modal");
                    deleteConfirmModal.classList.add("hidden");
                    deleteConfirmModal.classList.remove("flex");
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
                        deleteConfirmModal.classList.add("hidden");
                        deleteConfirmModal.classList.remove("flex");
                        
                        // Mostrar mensaje de éxito
                        const successMessage = document.createElement("div");
                        successMessage.className = "mb-6 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg";
                        successMessage.innerHTML = `
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-green-800">Productos eliminados exitosamente</h3>
                                    <p class="mt-2 text-sm text-green-700">Se han eliminado correctamente todos los productos de NewBytes.</p>
                                </div>
                            </div>
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
                        deleteConfirmModal.classList.add("hidden");
                        deleteConfirmModal.classList.remove("flex");
                        
                        // Mostrar mensaje de error
                        const errorMessage = document.createElement("div");
                        errorMessage.className = "mb-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg";
                        errorMessage.innerHTML = `
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-red-800">Error al eliminar productos</h3>
                                    <p class="mt-2 text-sm text-red-700">\${data.data || "Error desconocido al eliminar los productos."}</p>
                                </div>
                            </div>
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
                    deleteConfirmModal.classList.add("hidden");
                    deleteConfirmModal.classList.remove("flex");
                    
                    // Mostrar mensaje de error con Tailwind CSS
                    const errorMessage = document.createElement("div");
                    errorMessage.className = "mb-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg";
                    errorMessage.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-red-800">Error al procesar la solicitud</h3>
                                <p class="mt-2 text-sm text-red-700">Por favor, inténtalo de nuevo más tarde.</p>
                            </div>
                        </div>
                    `;
                    
                    // Insertar el mensaje de error después del botón de eliminar
                    const deleteButton = document.getElementById("delete-all-btn");
                    if (deleteButton && deleteButton.parentNode) {
                        deleteButton.parentNode.insertBefore(errorMessage, deleteButton.nextSibling);
                        
                        // Remover el mensaje después de 5 segundos
                        setTimeout(() => {
                            errorMessage.remove();
                        }, 5000);
                    }
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
                updateDescriptionModal.classList.remove("hidden");
                updateDescriptionModal.classList.add("flex");
            });

            cancelUpdateDescriptionBtn.addEventListener("click", function() {
                updateDescriptionModal.classList.add("hidden");
                updateDescriptionModal.classList.remove("flex");
            });

            closeSuccessModalBtn.addEventListener("click", function() {
                successConfirmModal.classList.add("hidden");
                successConfirmModal.classList.remove("flex");
            });

            closeFailModalBtn.addEventListener("click", function() {
                failConfirmModal.classList.add("hidden");
                failConfirmModal.classList.remove("flex");
            });

            updateDescriptionModal.addEventListener("click", function(event) {
                if (event.target === this) {
                    updateDescriptionModal.classList.add("hidden");
                    updateDescriptionModal.classList.remove("flex");
                }
            });

            confirmUpdateDescriptionBtn.addEventListener("click", function() {
                // Cambiar el texto del botón al spinner de Tailwind CSS y deshabilitarlo
                confirmUpdateDescriptionBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-indigo-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Procesando...
                `;
                confirmUpdateDescriptionBtn.disabled = true;

                var formData = new FormData(confirmUpdateDescriptionForm);
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        // Mostrar mensaje específico para sincronización de descripciones
                        const stats = data.data.stats;
                        const totalProducts = (stats.updated || 0) + (stats.created || 0);
                        const successMessage = totalProducts > 0 
                            ? `Las descripciones de ${totalProducts} productos fueron sincronizadas correctamente desde la API de NewBytes.`
                            : `La sincronización se completó. No se encontraron productos para actualizar.`;
                        
                        const successModalContent = document.querySelector("#success-confirm-modal div");
                        if (successModalContent) {
                            successModalContent.innerHTML = `
                                <div class="flex justify-center mb-4">
                                    <svg class="h-12 w-12 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h2 class="text-xl font-semibold text-gray-800 mb-2">Descripciones sincronizadas correctamente</h2>
                                <p class="text-gray-600 mb-4">${successMessage}</p>
                                <button type="button" id="close-success-modal-btn" class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-green-500 hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 transition-all duration-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    Cerrar
                                </button>
                            `;
                        }
                        
                        successConfirmModal.classList.remove("hidden");
                        successConfirmModal.classList.add("flex");
                        updateDescriptionModal.classList.add("hidden");
                        updateDescriptionModal.classList.remove("flex");
                        
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
                                successConfirmModal.classList.add("hidden");
                                successConfirmModal.classList.remove("flex");
                            });
                        }
                    } else {
                        failConfirmModal.classList.remove("hidden");
                        failConfirmModal.classList.add("flex");
                    }
                }).catch(error => {
                    console.error("Error:", error);
                }).finally(() => {
                    // Restaurar el texto del botón y habilitarlo
                    confirmUpdateDescriptionBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                        </svg>
                        Actualizar Descripciones
                    `;
                    confirmUpdateDescriptionBtn.disabled = false;
                });
            });
        }
    });
    </script>';
}
