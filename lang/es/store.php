<?php

return [
    'hero_title' => 'Tu espacio de trabajo, listo en minutos',
    'hero_subtitle' => 'Escritorios seguros, potencia GPU y aplicaciones listas para usar, desde cualquier navegador.',
    'all' => 'Todo',
    'from' => 'desde',
    'configure' => 'Elegir',
    'empty_kind' => 'Aún no hay nada aquí.',
    'spec' => ['cores' => ':n vCPU'],
    'admin' => ['provider' => 'Plataforma en la tienda', 'auto_provider' => 'Automática (según el módulo)', 'kind' => 'Sección de la tienda', 'auto' => 'Automática (según persistente)', 'kind_hint' => 'Dónde aparece este producto en la tienda.', 'gpu' => 'GPU que se muestra en la tarjeta (opcional)'],
    'where' => 'Dónde corre',
    'where_sub' => 'Elige la plataforma; puedes combinar varias.',
    'any_platform' => 'Cualquier plataforma',
    'soon' => 'Pronto',
    'live' => 'Disponible',
    'what' => 'Qué necesitas',
    'no_match' => 'Aún no hay nada con esta combinación.',
    'reset' => 'Quitar filtros',
    'pills' => ['Acceso Zero Trust', 'Desde cualquier navegador', 'Multi-nube'],
    'provider_group' => ['public' => 'Nubes públicas', 'private' => 'Nubes privadas', 'hypervisor' => 'Hipervisores', 'container' => 'Contenedores'],
    'kind' => [
        'vdi-persistent' => [
            'name' => 'Escritorios persistentes',
            'blurb' => 'Tu propio escritorio Windows o Linux. Archivos, aplicaciones y ajustes se conservan entre sesiones.',
        ],
        'vdi-ephemeral' => [
            'name' => 'Escritorios no persistentes',
            'blurb' => 'Un escritorio limpio en cada sesión. Ideal para laboratorios, capacitación y externos.',
        ],
        'gpu' => [
            'name' => 'GPU y alto rendimiento',
            'blurb' => 'Gráficos y cómputo dedicados para diseño, 3D, video y procesamiento pesado.',
        ],
        'app-oneclick' => [
            'name' => 'Apps de un clic',
            'blurb' => 'Software instalado bajo demanda en tu escritorio, listo para usar.',
        ],
        'app-docker' => [
            'name' => 'Apps en contenedor',
            'blurb' => 'Aplicaciones de escritorio entregadas desde un contenedor, sin instalar nada.',
        ],
        'app-browser' => [
            'name' => 'Navegador aislado',
            'blurb' => 'Una sesión de navegador sellada para sitios riesgosos y apps web. Nada queda en el equipo.',
        ],
        'other' => [
            'name' => 'Otros servicios',
            'blurb' => '',
        ],
    ],
];
