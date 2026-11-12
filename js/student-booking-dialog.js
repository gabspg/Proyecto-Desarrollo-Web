document.addEventListener("click", function (event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    const openButton = event.target.closest("[data-dialog-target]");
    if (openButton) {
        const dialog = document.getElementById(openButton.dataset.dialogTarget);
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
            dialog.classList.add("show");
            dialog.setAttribute("aria-hidden", "false");
        }
        return;
    }

    const closeButton = event.target.closest("[data-dialog-dismiss]");
    if (closeButton) {
        const dialog = closeButton.closest("dialog");
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
            dialog.classList.remove("show");
            dialog.setAttribute("aria-hidden", "true");
        }
        return;
    }

    if (event.target instanceof HTMLDialogElement && event.target.open) {
        event.target.close();
    }
});

document.addEventListener("close", function (event) {
    if (event.target instanceof HTMLDialogElement) {
        event.target.classList.remove("show");
        event.target.setAttribute("aria-hidden", "true");
    }
}, true);
