<?php
require_once __DIR__ . "/../seguridad/sesion.php";

$resultado = $_SESSION["registro_resultado"] ?? null;
unset($_SESSION["registro_resultado"]);

if (!is_array($resultado) || !isset($resultado["titulo"], $resultado["mensaje"])) {
    header("Location: registro.php");
    exit;
}

function mostrarMensaje($titulo, $mensaje, $tipo = 'error', $textoBoton = 'Volver al registro', $enlace = '../autenticacion/registro.php', $cspNonce = '')
{
    $titulo = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $mensaje = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    $textoBoton = htmlspecialchars($textoBoton, ENT_QUOTES, 'UTF-8');
    $enlace = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

    $clase = $tipo === 'exito' ? 'guardar-exito' : 'guardar-error';
    $icono = $tipo === 'exito' ? '&#10003;' : '!';

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo} - TutorLink</title>

    <style nonce="{$cspNonce}">
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            padding: 12px;
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(135deg, #102B3F, #176B52);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* CONTENEDOR HORIZONTAL */

        .guardar-container {
            width: 100%;
            max-width: 900px;
            min-height: 390px;
            display: grid;
            grid-template-columns: 45% 55%;
            background: #244b3b;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.25);
        }

        /* PANEL IZQUIERDO */

        .guardar-visual {
            min-width: 0;
            padding: 30px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .guardar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #102B3F;
            font-size: 23px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .guardar-logo-icono {
            color: #176B52;
            font-size: 28px;
        }

        .guardar-visual h1 {
            font-size: 34px;
            font-weight: 700;
            color: #102B3F;
            margin: 0 0 12px;
        }

        .guardar-visual p {
            font-size: 14px;
            line-height: 1.6;
            color: #176B52;
            max-width: 280px;
            margin: 0;
        }

        /* PANEL DERECHO */

        .guardar-panel {
            min-width: 0;
            padding: 35px 30px;
            background: #244b3b;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* ICONO */

        .guardar-icono {
            width: 78px;
            height: 78px;
            margin-bottom: 20px;
            background: #376b58;
            border: 2px solid #69b4b1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8bd4cf;
            font-size: 40px;
            font-weight: 700;
        }

        .guardar-error .guardar-icono {
            background: #633c40;
            border-color: #e9a3a3;
            color: #ffd0d0;
        }

        /* MENSAJES */

        .guardar-panel h2 {
            font-size: 25px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 12px;
        }

        .guardar-panel p {
            font-size: 14px;
            line-height: 1.7;
            color: #d1e3da;
            max-width: 350px;
            margin: 0 0 25px;
        }

        /* BOTÓN */

        .guardar-boton {
            width: 100%;
            max-width: 270px;
            min-height: 44px;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            background: #69b4b1;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease,
                        transform 0.2s ease;
        }

        .guardar-boton:hover {
            background: #4b9e9a;
            transform: translateY(-2px);
        }

        .guardar-boton:focus-visible {
            outline: 2px solid #ffffff;
            outline-offset: 3px;
        }

        /* PIE */

        .guardar-footer {
            margin-top: 24px;
            font-size: 10px;
            color: #a9c5b7;
        }

        /* TABLETS */

        @media (max-width: 850px) {
            .guardar-container {
                grid-template-columns: 43% 57%;
            }

            .guardar-visual {
                padding: 20px;
            }

            .guardar-panel {
                padding: 25px 20px;
            }

            .guardar-visual h1 {
                font-size: 27px;
            }
        }

        /* CELULARES - SIEMPRE HORIZONTAL */

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .guardar-container {
                grid-template-columns: 40% 60%;
                min-height: 330px;
                border-radius: 20px;
            }

            .guardar-visual {
                padding: 12px;
            }

            .guardar-logo {
                font-size: 14px;
                gap: 4px;
                flex-wrap: wrap;
                margin-bottom: 15px;
            }

            .guardar-logo-icono {
                font-size: 19px;
            }

            .guardar-visual h1 {
                font-size: 19px;
            }

            .guardar-visual p {
                font-size: 10px;
            }

            .guardar-panel {
                padding: 20px 12px;
            }

            .guardar-icono {
                width: 55px;
                height: 55px;
                font-size: 28px;
                margin-bottom: 14px;
            }

            .guardar-panel h2 {
                font-size: 19px;
            }

            .guardar-panel p {
                font-size: 11px;
                margin-bottom: 18px;
            }

            .guardar-boton {
                font-size: 11px;
                padding: 10px;
            }

            .guardar-footer {
                font-size: 9px;
            }
        }

        /* CELULARES PEQUEÑOS */

        @media (max-width: 400px) {
            .guardar-container {
                grid-template-columns: 35% 65%;
            }

            .guardar-visual {
                padding: 8px;
            }

            .guardar-logo {
                font-size: 12px;
            }

            .guardar-visual h1 {
                font-size: 17px;
            }

            .guardar-visual p {
                font-size: 9px;
            }

            .guardar-panel {
                padding: 15px 9px;
            }

            .guardar-panel h2 {
                font-size: 17px;
            }
        }
    </style>
</head>

<body>

    <main class="guardar-container {$clase}">

        <!-- PANEL IZQUIERDO -->

        <section class="guardar-visual">

            <div class="guardar-logo">
                <span class="guardar-logo-icono">&#10022;</span>
                TutorLink
            </div>

            <h1>¡Bienvenido!</h1>

            <p>
                Tu espacio para aprender,
                compartir conocimientos
                y crecer académicamente.
            </p>

        </section>

        <!-- PANEL DERECHO -->

        <section class="guardar-panel">

            <div class="guardar-icono">
                {$icono}
            </div>

            <h2>{$titulo}</h2>

            <p>{$mensaje}</p>

            <a href="{$enlace}" class="guardar-boton">
                {$textoBoton}
            </a>

            <div class="guardar-footer">
                TutorLink | Sistema de Tutorías Académicas
            </div>

        </section>

    </main>

</body>
</html>
HTML;

    exit;
}

mostrarMensaje(
    $resultado["titulo"],
    $resultado["mensaje"],
    $resultado["tipo"] ?? "error",
    $resultado["texto_boton"] ?? "Volver al registro",
    $resultado["enlace"] ?? "registro.php",
    $cspNonce
);
