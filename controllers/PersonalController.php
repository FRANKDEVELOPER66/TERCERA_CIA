<?php

namespace Controllers;

use Exception;
use Model\Grados;
use MVC\Router;

class PersonalController
{
    public static function index(Router $router)
    {
        $grados = Grados::obtenerGrados();

        $router->render('personal/index', [
            'grados' => $grados
        ]);
    }
}
