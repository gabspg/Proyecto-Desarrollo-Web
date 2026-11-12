// gestiona la apertura y el cierre de ventanas emergentes, 
// permitiendo mostrarlas y ocultarlas mediante botones 
// o al hacer clic fuera de ellas

// controla la apertura y cierre de las ventanas emergentes (dialog)
document.addEventListener("click", function (event) {

    // verifica que el elemento seleccionado sea válido
    if (!(event.target instanceof Element)) {
        return;
    }

    // busca el botón que abre una ventana
    const openButton = event.target.closest("[data-dialog-target]");
    if (openButton) {

        // obtiene la ventana por medio de su id
        const dialog = document.getElementById(openButton.dataset.dialogTarget);

        // abre la ventana y muestra su estado
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
            dialog.classList.add("show");
            dialog.setAttribute("aria-hidden", "false");
        }
        return;
    }

    // busca el botón que cierra la ventana
    const closeButton = event.target.closest("[data-dialog-dismiss]");
    if (closeButton) {

        // obtiene la ventana y la cierra
        const dialog = closeButton.closest("dialog");
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
            dialog.classList.remove("show");
            dialog.setAttribute("aria-hidden", "true");
        }
        return;
    }

    // permite cerrar la ventana al hacer clic fuera de su contenido
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        event.target.close();
    }
});

// actualiza el estado visual cuando la ventana se cierra
document.addEventListener("close", function (event) {
    if (event.target instanceof HTMLDialogElement) {
        event.target.classList.remove("show");
        event.target.setAttribute("aria-hidden", "true");
    }
}, true);