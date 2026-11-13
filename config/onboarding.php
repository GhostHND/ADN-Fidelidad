<?php

return [
    'admin-business' => [
        'area' => 'admin',
        'routes' => ['admin.dashboard', 'admin.businesses', 'admin.businesses.create', 'admin.businesses.show'],
        'title' => 'Crear y administrar un negocio',
        'steps' => [
            ['title' => 'Abre Negocios', 'text' => 'En el menú de Administración ADN entra en Negocios y selecciona Nuevo negocio. Esta acción corresponde al superadministrador, no al dueño del negocio.'],
            ['title' => 'Registra al propietario', 'text' => 'Escribe el nombre del negocio, nombre, correo y contraseña del propietario (mínimo 10 caracteres). Si el correo ya pertenece a un usuario, se vincula esa cuenta y no se cambia su contraseña.'],
            ['title' => 'Configura la suscripción', 'text' => 'Selecciona el estado y las funcionalidades necesarias, incluida Google Wallet si corresponde. El ID de Software Master es opcional para el alta manual.'],
            ['title' => 'Entra al negocio', 'text' => 'Pulsa Crear negocio. En el detalle puedes usar Entrar como este negocio o pedir al propietario que inicie sesión con su propia cuenta.'],
        ],
    ],
    'business-welcome' => [
        'area' => 'business', 'permission' => 'dashboard.view',
        'routes' => ['app.dashboard'],
        'title' => 'Bienvenido a tu panel',
        'steps' => [
            ['title' => 'Tu negocio', 'text' => 'El negocio se da de alta desde Administración ADN. Si tienes acceso a Mi negocio, revisa sus datos, logotipo, contacto y términos.'],
            ['title' => 'Prepara la tarjeta', 'text' => 'Si tu rol permite administrar Tarjetas, crea una tarjeta, configura sus reglas y una recompensa vigente, y actívala. Cada sección tiene una guía disponible en Ayuda.'],
            ['title' => 'Comparte y atiende', 'text' => 'Comparte la página pública para registrar clientes. Al escanear su tarjeta podrás asignar sellos y consultar los premios disponibles según tus permisos.'],
            ['title' => 'Vuelve a consultar', 'text' => 'En cada sección que tenga una guía, usa el botón Ayuda para volver a abrir las instrucciones cuando lo necesites.'],
        ],
    ],
    'business-profile' => [
        'area' => 'business', 'permission' => 'business.manage',
        'routes' => ['app.business'],
        'title' => 'Configura tu negocio',
        'steps' => [
            ['title' => 'Completa los datos', 'text' => 'Revisa el nombre, contacto, dirección y descripción. Estos datos se utilizan en tu página pública y tus tarjetas.'],
            ['title' => 'Personaliza tu imagen', 'text' => 'Elige los colores y sube tu logotipo en PNG, JPG o WebP de hasta 2 MB. Completa el aviso de privacidad y los términos del programa.'],
            ['title' => 'Guarda y comprueba', 'text' => 'Pulsa Guardar cambios y abre Ver página pública. Si tienes varias sucursales, agrégalas abajo; este paso es opcional.'],
        ],
    ],
    'program-create' => [
        'area' => 'business', 'permission' => 'programs.manage',
        'routes' => ['app.programs', 'app.programs.create'],
        'title' => 'Crea tu tarjeta de fidelidad',
        'steps' => [
            ['title' => 'Inicia una tarjeta', 'text' => 'En Tarjetas pulsa Nueva tarjeta. Si no aparece la opción, revisa el estado y los límites de la suscripción.'],
            ['title' => 'Define las reglas', 'text' => 'Escribe el nombre, la meta de sellos, cómo se ganan y el máximo por operación. Elige qué sucede con el saldo cuando se completa el ciclo.'],
            ['title' => 'Diseña y guarda', 'text' => 'Selecciona colores y, si quieres, imágenes para sellos conseguidos y pendientes. Revisa la vista previa y pulsa Crear tarjeta.'],
            ['title' => 'Agrega una recompensa', 'text' => 'La tarjeta se guarda como borrador. En la pantalla siguiente crea al menos una recompensa activa y después pulsa Activar.'],
        ],
    ],
    'program-rewards' => [
        'area' => 'business', 'permission' => 'programs.manage',
        'routes' => ['app.programs.edit'],
        'title' => 'Configura y activa las recompensas',
        'steps' => [
            ['title' => 'Ubica Nueva recompensa', 'text' => 'En la parte inferior de la tarjeta busca Recompensas y el formulario Nueva recompensa.'],
            ['title' => 'Define el premio', 'text' => 'Escribe el nombre, los sellos requeridos y el tipo. Selecciona Activa, revisa si descuenta sellos y añade las condiciones que correspondan.'],
            ['title' => 'Revisa su vigencia', 'text' => 'Si estableces una fecha de vencimiento, asegúrate de que la recompensa siga vigente cuando el cliente complete la meta. Una recompensa vencida no se genera.'],
            ['title' => 'Guarda y activa', 'text' => 'Pulsa Agregar recompensa. Después usa Activar en la parte superior de la tarjeta. Para servicios o promociones, agrega también las reglas de sellos correspondientes.'],
        ],
    ],
    'customers' => [
        'area' => 'business', 'permission' => 'customers.view',
        'routes' => ['app.customers', 'app.customers.show'],
        'title' => 'Registra y consulta clientes',
        'steps' => [
            ['title' => 'Comparte el registro', 'text' => 'El responsable puede abrir Ver página pública desde Mi negocio y compartir ese enlace. El cliente completa sus datos y acepta el aviso de privacidad para obtener su tarjeta.'],
            ['title' => 'Guarda la tarjeta', 'text' => 'El cliente puede usar el QR de la página web. Google Wallet aparece cuando está habilitado; en iPhone puede guardar la tarjeta web en la pantalla de inicio.'],
            ['title' => 'Consulta recompensas', 'text' => 'En Clientes puedes buscar por nombre, correo o teléfono y filtrar Con recompensa disponible. Abre Ver para consultar recompensas y movimientos.'],
        ],
    ],
    'scan' => [
        'area' => 'business', 'permission' => 'stamps.assign',
        'routes' => ['app.scan'],
        'title' => 'Asigna sellos y canjea recompensas',
        'steps' => [
            ['title' => 'Identifica al cliente', 'text' => 'Pide al cliente el QR de su tarjeta. En Escanear, activa la cámara o usa un lector USB o el código manual. Si corresponde, elige la sucursal.'],
            ['title' => 'Registra los sellos', 'text' => 'Comprueba el nombre del cliente y captura la cantidad, el monto o la regla solicitada. Pulsa Asignar sellos una sola vez.'],
            ['title' => 'Revisa el resultado', 'text' => 'Cuando se genere una recompensa verás un aviso destacado y la lista de recompensas disponibles. Si el ciclo termina sin recompensa, revisa la vigencia y configuración con el responsable.'],
            ['title' => 'Canjea con el cliente', 'text' => 'Si tienes permiso para canjear, localiza el premio en Recompensas disponibles y pulsa Canjear cuando el cliente lo utilice. Comprueba el resultado antes de entregar el premio. Si no tienes permiso, solicita ayuda al responsable.'],
            ['title' => 'Continúa atendiendo', 'text' => 'Pulsa Escanear otra tarjeta para iniciar con otro cliente. Las actualizaciones de Wallet pueden tardar y dependen de la conexión y del servicio.'],
        ],
    ],
];
