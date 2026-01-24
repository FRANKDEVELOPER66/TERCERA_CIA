<?php

namespace Model;

class Personal extends ActiveRecord
{
    protected static $tabla = 'personal';
    protected static $idTabla = 'id_personal';
    protected static $columnasDB = ['nombres', 'apellidos', 'id_grado', 'id_grupo_descanso', 'tipo', 'es_encargado', 'activo', 'fecha_ingreso', 'observaciones', 'created_at', 'updated_at'];

    public $id_personal;
    public $nombres;
    public $apellidos;
    public $id_grado;
    public $id_grupo_descanso;
    public $tipo;
    public $es_encargado;
    public $activo;
    public $fecha_ingreso;
    public $observaciones;
    public $created_at;
    public $updated_at;

    public function __construct($args = [])
    {
        $this->id_personal = $args['id_personal'] ?? null;
        $this->nombres = $args['usu_nombre'] ?? '';
        $this->apellidos = $args['usu_apellidos'] ?? '';
        $this->id_grado = $args['id_grado'] ?? null;
        $this->id_grupo_descanso = $args['id_grupo_descanso'] ?? null;
        $this->tipo = $args['tipo'] ?? '';
        $this->es_encargado = $args['es_encargado'] ?? 0;
        $this->activo = $args['activo'] ?? 1;
        $this->fecha_ingreso = $args['fecha_ingreso'] ?? null;
        $this->observaciones = $args['observaciones'] ?? '';
        $this->created_at = $args['created_at'] ?? date('Y-m-d H:i:s');
        $this->updated_at = $args['updated_at'] ?? date('Y-m-d H:i:s');
    }

    public static function traerPersonal()
    {
        $sql = "SELECT * FROM personal ORDER BY id_personal ASC";
        return self::fetchArray($sql);
    }
}
