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
        $sql = "SELECT * FROM bhr_grados ORDER BY orden ASC";
        return self::fetchArray($sql);
    }

    public static function obtenerPorTipo($tipo)
    {
        $sql = "SELECT * FROM bhr_grados WHERE tipo = :tipo ORDER BY orden ASC";
        return self::fetchArray($sql, [':tipo' => $tipo]);
    }
}
