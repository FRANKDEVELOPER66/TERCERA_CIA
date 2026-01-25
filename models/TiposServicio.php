<?php

namespace Model;

class TiposServicio extends ActiveRecord
{
    protected static $tabla = 'tipos_servicio';
    protected static $idTabla = 'id_tipo_servicio';
    protected static $columnasDB = [
        'nombre',
        'descripcion',
        'tipo_personal',
        'cantidad_especialistas',
        'cantidad_soldados',
        'requiere_oficial',
        'duracion_horas',
        'prioridad_asignacion'
    ];

    public $id_tipo_servicio;
    public $nombre;
    public $descripcion;
    public $tipo_personal;
    public $cantidad_especialistas;
    public $cantidad_soldados;
    public $requiere_oficial;
    public $duracion_horas;
    public $prioridad_asignacion;

    public function __construct($args = [])
    {
        $this->id_tipo_servicio = $args['id_tipo_servicio'] ?? null;
        $this->nombre = $args['nombre'] ?? '';
        $this->descripcion = $args['descripcion'] ?? '';
        $this->tipo_personal = $args['tipo_personal'] ?? 'AMBOS';
        $this->cantidad_especialistas = $args['cantidad_especialistas'] ?? 0;
        $this->cantidad_soldados = $args['cantidad_soldados'] ?? 0;
        $this->requiere_oficial = $args['requiere_oficial'] ?? 0;
        $this->duracion_horas = $args['duracion_horas'] ?? 24;
        $this->prioridad_asignacion = $args['prioridad_asignacion'] ?? 1;
    }

    public static function obtenerTodos()
    {
        $sql = "SELECT * FROM tipos_servicio ORDER BY prioridad_asignacion ASC";
        return self::fetchArray($sql);
    }
}
