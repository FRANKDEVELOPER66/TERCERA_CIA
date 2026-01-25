<?php

namespace Model;

class GruposDescanso extends ActiveRecord
{
    protected static $tabla = 'grupos_descanso';
    protected static $idTabla = 'id_grupo'; // ⭐ CORREGIDO
    protected static $columnasDB = ['nombre', 'tipo', 'color'];

    public $id_grupo; // ⭐ CORREGIDO
    public $nombre;
    public $tipo;
    public $color;

    public function __construct($args = [])
    {
        $this->id_grupo = $args['id_grupo'] ?? null; // ⭐ CORREGIDO
        $this->nombre = $args['nombre'] ?? '';
        $this->tipo = $args['tipo'] ?? '';
        $this->color = $args['color'] ?? '';
    }

    public static function obtenerGrupos()
    {
        $sql = "SELECT * FROM grupos_descanso ORDER BY tipo, nombre ASC";
        return self::fetchArray($sql);
    }

    public static function obtenerPorTipo($tipo)
    {
        $sql = "SELECT * FROM grupos_descanso WHERE tipo = :tipo ORDER BY nombre ASC";
        return self::fetchArray($sql, [':tipo' => $tipo]);
    }
}
