<?php

namespace Model;

class CalendarioDescansos extends ActiveRecord
{
    protected static $tabla = 'calendario_descansos';
    protected static $idTabla = 'id_calendario';
    protected static $columnasDB = [
        'id_grupo_descanso',
        'fecha_inicio',
        'fecha_fin',
        'estado'
    ];

    public $id_calendario;
    public $id_grupo_descanso;
    public $fecha_inicio;
    public $fecha_fin;
    public $estado;

    public function __construct($args = [])
    {
        $this->id_calendario = $args['id_calendario'] ?? null;
        $this->id_grupo_descanso = $args['id_grupo_descanso'] ?? null;
        $this->fecha_inicio = $args['fecha_inicio'] ?? '';
        $this->fecha_fin = $args['fecha_fin'] ?? '';
        $this->estado = $args['estado'] ?? 'PROGRAMADO';
    }

    /**
     * Obtiene el calendario activo para una fecha
     */
    public static function obtenerPorFecha($fecha)
    {
        $sql = "SELECT cd.*, gd.nombre as grupo_nombre, gd.tipo
                FROM calendario_descansos cd
                INNER JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
                WHERE :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
                ORDER BY gd.tipo, gd.nombre";

        return self::fetchArray($sql, [':fecha' => $fecha]);
    }

    /**
     * Genera calendario de descansos para todos los grupos
     */
    public static function generarCalendario($fecha_inicio, $ciclos = 3)
    {
        $grupos = GruposDescanso::all();
        $fecha_actual = new \DateTime($fecha_inicio);

        foreach ($grupos as $grupo) {
            $fecha_inicio_grupo = clone $fecha_actual;

            for ($i = 0; $i < $ciclos; $i++) {
                // 20 días adentro (no se registra)
                $fecha_inicio_grupo->modify('+20 days');

                // 10 días de descanso
                $fecha_inicio_descanso = clone $fecha_inicio_grupo;
                $fecha_fin_descanso = clone $fecha_inicio_grupo;
                $fecha_fin_descanso->modify('+9 days');

                $calendario = new self([
                    'id_grupo_descanso' => $grupo->id_grupo,
                    'fecha_inicio' => $fecha_inicio_descanso->format('Y-m-d'),
                    'fecha_fin' => $fecha_fin_descanso->format('Y-m-d'),
                    'estado' => 'PROGRAMADO'
                ]);

                $calendario->crear();

                // Avanzar al siguiente ciclo (10 días de descanso)
                $fecha_inicio_grupo->modify('+10 days');
            }
        }
    }
}
