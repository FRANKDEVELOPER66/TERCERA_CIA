<?php

namespace Model;

class Grados extends ActiveRecord
{
    protected static $tabla = 'bhr_grados'; // ⭐ CORREGIDO con prefijo
    protected static $idTabla = 'id_grado';
    protected static $columnasDB = ['nombre', 'tipo', 'orden']; // ⭐ AGREGADO 'nombre'

    public $id_grado;
    public $nombre; // ⭐ AGREGADO
    public $tipo;
    public $orden;

    public function __construct($args = [])
    {
        $this->id_grado = $args['id_grado'] ?? null;
        $this->nombre = $args['nombre'] ?? ''; // ⭐ AGREGADO
        $this->tipo = $args['tipo'] ?? '';
        $this->orden = $args['orden'] ?? '';
    }

    public static function obtenerGrados()
    {
        $sql = "SELECT * FROM bhr_grados ORDER BY tipo, orden";
        return self::fetchArray($sql);
    }
}
