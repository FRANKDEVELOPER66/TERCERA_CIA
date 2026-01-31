<?php

namespace Model;

class Personal extends ActiveRecord
{
    protected static $tabla = 'bhr_personal'; // ⭐ CORREGIDO con prefijo
    protected static $idTabla = 'id_personal';
    protected static $columnasDB = [
        'nombres',
        'apellidos',
        'id_grado',
        'id_grupo_descanso',
        'tipo',
        'es_encargado',
        'activo',
        'fecha_ingreso',
        'observaciones'
    ];

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

    public function __construct($args = [])
    {
        $this->id_personal = $args['id_personal'] ?? null;
        $this->nombres = $args['nombres'] ?? '';
        $this->apellidos = $args['apellidos'] ?? '';
        $this->id_grado = $args['id_grado'] ?? null;
        $this->id_grupo_descanso = $args['id_grupo_descanso'] ?? null;
        $this->tipo = $args['tipo'] ?? '';
        $this->es_encargado = $args['es_encargado'] ?? 0;
        $this->activo = $args['activo'] ?? 1;
        $this->fecha_ingreso = $args['fecha_ingreso'] ?? date('Y-m-d');
        $this->observaciones = $args['observaciones'] ?? '';
    }

    public static function traerPersonal()
    {
        $sql = "SELECT 
                    p.*,
                    g.nombre as grado_nombre,
                    gd.nombre as grupo_nombre
                FROM bhr_personal p
                LEFT JOIN bhr_grados g ON p.id_grado = g.id_grado
                LEFT JOIN grupos_descanso gd ON p.id_grupo_descanso = gd.id_grupo
                ORDER BY p.apellidos, p.nombres";

        return self::fetchArray($sql);
    }


    public static function obtenerPersonalActivo()
    {
        $sql = "SELECT 
            p.id_personal,
            p.nombres,
            p.apellidos,
            p.tipo,              -- ✅ Asegúrate de que esto esté aquí
            g.nombre as grado,
            g.orden as orden_grado,
            p.id_grupo_descanso
        FROM bhr_personal p
        INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
        WHERE p.activo = 1
        ORDER BY 
            CASE p.tipo
                WHEN 'OFICIAL' THEN 1
                WHEN 'ESPECIALISTA' THEN 2
                WHEN 'TROPA' THEN 3
            END,
            g.orden ASC,
            p.apellidos ASC,
            p.nombres ASC";

        $personal = self::fetchArray($sql, []);

        // 🔍 DEBUG - Ver qué se está devolviendo
        error_log("📋 Personal obtenido: " . count($personal) . " registros");
        if (count($personal) > 0) {
            error_log("👤 Primer registro: " . json_encode($personal[0]));
        }

        return $personal;
    }
}
