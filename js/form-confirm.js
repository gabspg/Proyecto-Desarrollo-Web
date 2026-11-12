// verifica si el formulario necesita confirmación antes de enviarse
document.addEventListener("submit", function (event) {
    const form = event.target;

    // comprueba que el elemento enviado sea un formulario
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    // obtiene el mensaje de confirmación del formulario o del botón
    const confirmation = form.dataset.confirm ||
        (event.submitter && event.submitter.dataset.confirm);

    // muestra la confirmación y cancela el envío si el usuario no acepta
    if (confirmation && !window.confirm(confirmation)) {
        event.preventDefault();
    }
});