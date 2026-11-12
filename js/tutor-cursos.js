document.addEventListener("DOMContentLoaded", function () {
    const modalidad = document.getElementById("modalidad");
    const etiqueta = document.getElementById("etiquetaLugar");
    const campo = document.getElementById("lugar_link");
    const ayuda = document.getElementById("ayudaLugar");

    function actualizarModalidad() {
        if (!modalidad || !etiqueta || !campo || !ayuda) {
            return;
        }

        if (modalidad.value === "VIRTUAL") {
            etiqueta.textContent = "Enlace de Google Meet";
            campo.type = "url";
            campo.placeholder = "https://meet.google.com/abc-defg-hij";
            ayuda.textContent = "Crea una reunión en Google Meet y pega aquí el enlace.";
        } else {
            etiqueta.textContent = "Lugar de la tutoría";
            campo.type = "text";
            campo.placeholder = "Ejemplo: Edificio A, aula 203";
            ayuda.textContent = "Indica dónde se realizará la tutoría presencial.";
        }
    }

    if (modalidad) {
        modalidad.addEventListener("change", actualizarModalidad);
        actualizarModalidad();
    }

    const tipo = document.getElementById("tipo_tutoria");
    const cupo = document.getElementById("cupo_maximo");
    const ayudaCupo = document.getElementById("ayudaCupo");

    function actualizarCupos() {
        if (!tipo || !cupo) {
            return;
        }

        if (tipo.value === "INDIVIDUAL") {
            cupo.value = 1;
            cupo.min = 1;
            cupo.readOnly = true;
            if (ayudaCupo) {
                ayudaCupo.textContent = "Las tutorías individuales tienen un solo cupo.";
            }
        } else {
            cupo.min = 2;
            if (Number(cupo.value) < 2) {
                cupo.value = 2;
            }
            cupo.readOnly = false;
            if (ayudaCupo) {
                ayudaCupo.textContent = "Las tutorías grupales requieren al menos 2 participantes.";
            }
        }
    }

    if (tipo) {
        tipo.addEventListener("change", actualizarCupos);
        actualizarCupos();
    }
});
