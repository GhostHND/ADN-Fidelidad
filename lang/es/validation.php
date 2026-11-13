<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'string' => 'El campo :attribute debe ser texto.',
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.', 'numeric' => 'El campo :attribute no debe ser mayor a :max.', 'file' => 'El archivo :attribute no debe superar :max KB.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'numeric' => 'El campo :attribute debe ser al menos :min.'],
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'unique' => 'Ese valor de :attribute ya está en uso.',
    'in' => 'El valor de :attribute no es válido.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'after_or_equal' => 'El campo :attribute debe ser posterior o igual a :date.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'accepted' => 'Debes aceptar :attribute.',
    'regex' => 'El formato de :attribute no es válido.',
    'image' => 'El archivo :attribute debe ser una imagen.',
    'mimetypes' => 'El archivo :attribute tiene un formato no permitido.',
    'between' => ['numeric' => 'El campo :attribute debe estar entre :min y :max.'],
    'attributes' => [
        'name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'phone' => 'teléfono', 'stamps_goal' => 'meta de sellos',
        'consent_privacy' => 'aviso de privacidad', 'stamps_required' => 'sellos requeridos', 'role' => 'rol', 'file' => 'archivo',
    ],
];
