<?php

namespace Model;

class Grados extends ActiveRecord
{
    protected static $tabla = 'grados';
    protected static $idTabla = 'id_grado';
    protected static $columnasDB = ['tipo', 'orden'];

    public $id_grado;
    public $tipo;
    public $orden;

    public function __construct($args = [])
    {
        $this->id_grado = $args['id_grado'] ?? null;
        $this->tipo = $args['tipo'] ?? '';
        $this->orden = $args['orden'] ?? '';
    }

    public static function obtenerGrados()
    {
        $sql = "SELECT * FROM grados";
        return self::fetchArray($sql);
    }
}
