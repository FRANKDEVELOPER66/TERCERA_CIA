<?php

namespace Model;

class ComisionOficial extends ActiveRecord
{
    protected static $tabla = 'comisiones_oficiales';
    protected static $idTabla = 'id_comision';
    protected static $columnasDB = [
        'id_personal',
        'fecha_inicio',
        'fecha_fin',
        'destino',
        'numero_oficio',
        'motivo',
        'created_by'
    ];

    public $id_comision;
    public $id_personal;
    public $fecha_inicio;
    public $fecha_fin;
    public $destino;
    public $numero_oficio;
    public $motivo;
    public $created_by;

    public function __construct($args = [])
    {
        $this->id_comision = $args['id_comision'] ?? null;
        $this->id_personal = $args['id_personal'] ?? null;
        $this->fecha_inicio = $args['fecha_inicio'] ?? '';
        $this->fecha_fin = $args['fecha_fin'] ?? '';
        $this->destino = $args['destino'] ?? 'Ciudad Capital';
        $this->numero_oficio = $args['numero_oficio'] ?? '';
        $this->motivo = $args['motivo'] ?? '';
        $this->created_by = $args['created_by'] ?? null;
    }
}