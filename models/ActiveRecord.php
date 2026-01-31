<?php

namespace Model;

use PDO;

class ActiveRecord
{

    // Base DE DATOS
    protected static $db;
    protected static $tabla = '';
    protected static $columnasDB = [];

    protected static $idTabla = '';

    // Alertas y Mensajes
    protected static $alertas = [];

    // Definir la conexión a la BD - includes/database.php
    public static function setDB($database)
    {
        self::$db = $database;
    }

    public static function setAlerta($tipo, $mensaje)
    {
        static::$alertas[$tipo][] = $mensaje;
    }
    // Validación
    public static function getAlertas()
    {
        return static::$alertas;
    }

    public function validar()
    {
        static::$alertas = [];
        return static::$alertas;
    }

    // Registros - CRUD
    public function guardar()
    {
        $resultado = '';
        $id = static::$idTabla ?? 'id';
        if (!is_null($this->$id)) {
            // actualizar
            $resultado = $this->actualizar();
        } else {
            // Creando un nuevo registro
            $resultado = $this->crear();
        }
        return $resultado;
    }

    public static function all()
    {
        $query = "SELECT * FROM " . static::$tabla;
        $resultado = self::consultarSQL($query);

        // debuguear($resultado);
        return $resultado;
    }

    // Busca un registro por su id
    public static function find($id = [])
    {
        $idQuery = static::$idTabla ?? 'id';
        $query = "SELECT * FROM " . static::$tabla;

        if (is_array(static::$idTabla)) {
            foreach (static::$idTabla as $key => $value) {
                if ($value == reset(static::$idTabla)) {
                    $query .= " WHERE $value = " . self::$db->quote($id[$value]);
                } else {
                    $query .= " AND $value = " . self::$db->quote($id[$value]);
                }
            }
        } else {

            $query .= " WHERE $idQuery = $id";
        }

        $resultado = self::consultarSQL($query);
        return array_shift($resultado);
    }

    // Obtener Registro
    public static function get($limite)
    {
        $query = "SELECT * FROM " . static::$tabla . " LIMIT ${limite}";
        $resultado = self::consultarSQL($query);
        return array_shift($resultado);
    }

    // Busqueda Where con Columna 
    public static function where($columna, $valor, $condicion = '=')
    {
        $query = "SELECT * FROM " . static::$tabla . " WHERE ${columna} ${condicion} '${valor}'";
        $resultado = self::consultarSQL($query);
        return  $resultado;
    }

    // SQL para Consultas Avanzadas.
    public static function SQL($consulta)
    {
        $query = $consulta;
        $resultado = self::$db->query($query);
        return $resultado;
    }

    // crea un nuevo registro
    public function crear()
    {
        // Sanitizar los datos
        $atributos = $this->sanitizarAtributos();

        // Insertar en la base de datos
        $query = " INSERT INTO " . static::$tabla . " ( ";
        $query .= join(', ', array_keys($atributos));
        $query .= " ) VALUES (";
        $query .= join(", ", array_values($atributos));
        $query .= " ) ";

        // AGREGAR LOGGING
        error_log("=== CREAR REGISTRO ===");
        error_log("Tabla: " . static::$tabla);
        error_log("Query: " . $query);
        error_log("Atributos: " . print_r($atributos, true));

        try {
            // Resultado de la consulta
            $resultado = self::$db->exec($query);

            error_log("Resultado exec: " . ($resultado !== false ? 'true' : 'false'));
            error_log("Last Insert ID: " . self::$db->lastInsertId());

            return [
                'resultado' =>  $resultado,
                'id' => self::$db->lastInsertId()
            ];
        } catch (\PDOException $e) {
            error_log("ERROR PDO en crear(): " . $e->getMessage());
            return [
                'resultado' => false,
                'id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    public function actualizar()
    {
        // Sanitizar los datos
        $atributos = $this->sanitizarAtributos();

        // Iterar para ir agregando cada campo de la BD
        $valores = [];
        foreach ($atributos as $key => $value) {
            $valores[] = "{$key}={$value}";
        }
        $id = static::$idTabla ?? 'id';

        $query = "UPDATE " . static::$tabla . " SET ";
        $query .=  join(', ', $valores);

        if (is_array(static::$idTabla)) {

            foreach (static::$idTabla as $key => $value) {
                if ($value == reset(static::$idTabla)) {
                    $query .= " WHERE $value = " . self::$db->quote($this->$value);
                } else {
                    $query .= " AND $value = " . self::$db->quote($this->$value);
                }
            }
        } else {
            $query .= " WHERE " . $id . " = " . self::$db->quote($this->$id) . " ";
        }

        // debuguear($query);

        $resultado = self::$db->exec($query);
        return [
            'resultado' =>  $resultado,
        ];
    }

    // Eliminar un registro - Toma el ID de Active Record
    public function eliminar()
    {
        $idQuery = static::$idTabla ?? 'id';
        $query = "DELETE FROM "  . static::$tabla . " WHERE $idQuery = " . self::$db->quote($this->id);
        $resultado = self::$db->exec($query);
        return $resultado;
    }

    public static function consultarSQL($query)
    {
        // Consultar la base de datos
        $resultado = self::$db->query($query);

        // Iterar los resultados
        $array = [];
        while ($registro = $resultado->fetch(PDO::FETCH_ASSOC)) {
            $array[] = static::crearObjeto($registro);
        }

        // liberar la memoria
        $resultado->closeCursor();

        // retornar los resultados
        return $array;
    }

    public static function fetchArray($sql, $params = [])
    {
        $stmt = self::$db->prepare($sql);

        // Encontrar todos los placeholders que efectivamente están en el SQL
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $placeholders_en_sql = array_unique($matches[0]);

        foreach ($placeholders_en_sql as $placeholder) {
            if ($placeholder === ':cantidad') {
                if (isset($params[$placeholder])) {
                    $stmt->bindValue($placeholder, (int)$params[$placeholder], PDO::PARAM_INT);
                }
            } else {
                if (isset($params[$placeholder])) {
                    $stmt->bindValue($placeholder, $params[$placeholder]);
                }
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * 🆕 MÉTODOS DE TRANSACCIONES
     */

    /**
     * Iniciar transacción
     */
    public static function beginTransaction()
    {
        try {
            if (self::$db) {
                self::$db->beginTransaction();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("❌ Error al iniciar transacción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirmar transacción
     */
    public static function commit()
    {
        try {
            if (self::$db && self::$db->inTransaction()) {
                self::$db->commit();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("❌ Error al hacer commit: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revertir transacción
     */
    public static function rollback()
    {
        try {
            if (self::$db && self::$db->inTransaction()) {
                self::$db->rollBack();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("❌ Error al hacer rollback: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si hay una transacción activa
     */
    public static function inTransaction()
    {
        try {
            return self::$db && self::$db->inTransaction();
        } catch (\Exception $e) {
            return false;
        }
    }


    public static function fetchFirst($sql, $params = [])
    {
        $stmt = self::$db->prepare($sql);

        // Encontrar todos los placeholders que efectivamente están en el SQL
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $placeholders_en_sql = array_unique($matches[0]);

        // Solo bindear los que existen en el SQL
        foreach ($placeholders_en_sql as $placeholder) {
            if (isset($params[$placeholder])) {
                $stmt->bindValue($placeholder, $params[$placeholder]);
            }
        }

        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    protected static function crearObjeto($registro)
    {
        $objeto = new static;

        foreach ($registro as $key => $value) {
            $key = strtolower($key);
            if (property_exists($objeto, $key)) {
                $objeto->$key = utf8_encode($value);
            }
        }

        return $objeto;
    }



    // Identificar y unir los atributos de la BD
    public function atributos()
    {
        $atributos = [];
        foreach (static::$columnasDB as $columna) {
            $columna = strtolower($columna);
            // No excluir el ID de la tabla si es NULL (para auto-increment)
            if ($columna === 'id') continue;

            // Solo excluir si es el ID específico de la tabla Y no es NULL
            if ($columna === static::$idTabla && !is_null($this->$columna)) {
                continue;
            }

            $atributos[$columna] = $this->$columna;
        }
        return $atributos;
    }

    public function sanitizarAtributos()
    {
        $atributos = $this->atributos();
        $sanitizado = [];

        foreach ($atributos as $key => $value) {
            // Si el valor es NULL, mantenerlo como NULL (no como cadena)
            if ($value === null || $value === '') {
                $sanitizado[$key] = 'NULL';
            } else {
                $sanitizado[$key] = self::$db->quote($value);
            }
        }

        return $sanitizado;
    }

    public function sincronizar($args = [])
    {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key) && !is_null($value)) {
                $this->$key = $value;
            }
        }
    }

    // Agregar este método a la clase ActiveRecord
    // ActiveRecord.php - ejecutarQuery CORREGIDO
    public static function ejecutarQuery($sql, $params = [])
    {
        try {
            $stmt = self::$db->prepare($sql);

            // Usar execute() directamente con el array de params
            // Esto resuelve automáticamente placeholders repetidos
            $resultado = $stmt->execute($params);

            return [
                'resultado' => $resultado,
                'filas_afectadas' => $stmt->rowCount()
            ];
        } catch (\PDOException $e) {
            error_log("Error en ejecutarQuery: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            return [
                'resultado' => false,
                'filas_afectadas' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
