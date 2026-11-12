document.addEventListener("submit", function (event) {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const confirmation = form.dataset.confirm ||
        (event.submitter && event.submitter.dataset.confirm);

    if (confirmation && !window.confirm(confirmation)) {
        event.preventDefault();
    }
});
