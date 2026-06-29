<?php

declare(strict_types=1);

/**
 * Librería de prompts reutilizables. Variables con la sintaxis {{var}}.
 * Cada proyecto puede agregar/editar sus prompts acá.
 */
return [
    'resumen' => "Resumí el siguiente texto en 3 puntos claros y concisos:\n\n{{texto}}",

    'traducir' => "Traducí al {{idioma}} el siguiente texto, manteniendo el tono:\n\n{{texto}}",

    'email-formal' => "Redactá un email formal y cordial dirigido a {{destinatario}} "
        . "sobre el siguiente asunto: {{asunto}}.",

    'clasificar' => "Clasificá el siguiente mensaje en UNA de estas categorías: {{categorias}}.\n"
        . "Respondé solo con la categoría.\n\nMensaje: {{texto}}",
];
