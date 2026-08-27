<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Da `$this->authorize()` a todos los controladores.
     *
     * En Laravel 12 la clase base viene vacía: el trait ya no se incluye solo,
     * y sin él `$this->authorize()` no existe y la llamada muere con un error
     * de método indefinido. Como eso pasa al ejecutar y no al compilar, una
     * comprobación de permisos escrita en un controlador nuevo podría no llegar
     * a hacerse nunca sin que nadie se enterase hasta pisarla.
     */
    use AuthorizesRequests;
}
