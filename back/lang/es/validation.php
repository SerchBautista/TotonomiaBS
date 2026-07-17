<?php

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'array' => 'El campo :attribute debe ser un arreglo.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'date' => 'El campo :attribute debe ser una fecha valida.',
    'email' => 'El campo :attribute debe ser una direccion de correo valida.',
    'exists' => 'El :attribute seleccionado no es valido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'integer' => 'El campo :attribute debe ser un numero entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener mas de :max elementos.',
        'file' => 'El campo :attribute no debe ser mayor que :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe ser mayor que :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe tener al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un numero.',
    'password' => [
        'letters' => 'La contraseña debe contener al menos una letra.',
        'mixed' => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
        'numbers' => 'La contraseña debe contener al menos un número.',
        'symbols' => 'La contraseña debe contener al menos un símbolo.',
        'uncompromised' => 'La contraseña proporcionada ha aparecido en una filtración de datos. Por favor elige una diferente.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'same' => 'El campo :attribute debe coincidir con :other.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'unique' => 'El valor de :attribute ya esta en uso.',
    'url' => 'El campo :attribute debe ser una URL valida.',

    'custom' => [],

    'attributes' => [
        'category_id' => 'categoría',
        'date' => 'fecha',
        'email' => 'correo electronico',
        'occurrence' => 'ocurrencia',
        'password' => 'contraseña',
        'paid_at' => 'fecha de pago',
        'paid_by_user_id' => 'pagador',
        'payment_instrument_id' => 'metodo de pago',
    ],
];
