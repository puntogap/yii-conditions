<?php
/**
 * @copyright Copyright (c) 2020-2021 PuntoGAP
 * @link http://puntogap.com/
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace PuntoGAP\YiiConditions;

use yii\helpers\Inflector;
use MatiasMuller\MethodsStacks\StackableCall;
use PuntoGAP\YiiConditions\ConditionsHandler;

/**
 * Conditions es el trait que se añade a las clases heredadas de
 * yii\base\ActiveQuery para añadirle la funcionalidad de Yii Conditions.
 *
 * @author Matías Müller <matias.muller@puntogap.com>
 */
trait Conditions
{
    use StackableCall;

    /**
     * Token para comunicarse con ConditionsHandler, generado desde
     * static::getConditionsHandlerToken()
     *
     * @var string|null
     */
    protected static $conditionsHandlerToken = null;

    /**
     * Obtiene el token que utilizará para comunicarse con ConditionsHandler. Persiste
     * el valor durante toda la ejecución en static::$conditionsHandlerToken
     *
     * @return string Token
     */
    protected static function getConditionsHandlerToken()
    {
        if (!static::$conditionsHandlerToken) {
            // Generación del token para vincular
            // Conditions con su Handler
            $token = (string)mt_rand();
            static::$conditionsHandlerToken = $token;
        }
        return static::$conditionsHandlerToken;
    }

    /**
     * Método de la pila de __call, que invoca el ConditionHandler para procesar
     * la llamada a los métodos de condiciones.
     *
     * @param  string $name
     * @param  array  $args
     * @return ActiveQuery|array|bool|string nextCallMethod constant
     */
    public function __callfromConditions($name, $args)
    {
        $token = static::getConditionsHandlerToken();
        return ConditionsHandler::handleCall($this, $name, $args, $token)
            ?: $this->nextCallMethod();
    }

    /**
     * Permite evaluar de igual forma en que Yii utiliza el método where(), pero
     * soporta "raw conditions".
     *
     * @param  array|string $condition
     * @param  array $options
     * @return ActiveQuery
     */
    public function whereCondition($condition, $options = [])
    {
        $token = static::getConditionsHandlerToken();
        return ConditionsHandler::handleCondition($this, $condition, $options, $token)
            ?: $this->nextCallMethod();
    }

    /**
     * Agrega una condición a la existente, combinándola con el operador `AND`
     *
     * @param  array|string $condition
     * @param  array $options
     * @return ActiveQuery
     */
    public function andWhereCondition($condition, $options = [])
    {
        $options['combineWith'] = 'and';
        return $this->whereCondition($condition, $options);
    }

    /**
     * Agrega una condición a la existente, combinándola con el operador `OR`
     *
     * @param  array|string $condition
     * @param  array $options
     * @return ActiveQuery
     */
    public function orWhereCondition($condition, $options = [])
    {
        $options['combineWith'] = 'or';
        return $this->whereCondition($condition, $options);
    }

    /**
     * Devuelve las "raw conditions" de los métodos de conditions usualmente
     * declarados como protected. A este método sólo se tiene acceso mediante el
     * token static::$conditionsHandlerToken que conoce el ConditionsHandler.
     *
     * @param  string $conditionName
     * @param  array  $args
     * @param  string $token
     * @return array|string raw condition
     */
    public function getRawCondition($conditionName, $args, $token)
    {
        // TODO: Implementar un error para lanzarlo en este lugar
        if (static::$conditionsHandlerToken !== $token) {
            return;
        }
        $conditionMethod = $this->methodFromConditionName($conditionName);
        return call_user_func_array([$this, $conditionMethod], $args);
    }

    /**
     * Si existe la condición, devuelve el nombre del método, sino retorna null.
     * FIXME: Falta validar que el nombre de la condición comience en minúscula.
     *
     * @param  string $conditionName
     * @return string|null
     */
    public function existsCondition($conditionName)
    {
        $conditionMethod = $this->methodFromConditionName($conditionName);
        return method_exists($this, $conditionMethod);
    }

    /**
     * Genera el nombre del método de la condición, dado un nombre de condición.
     *
     * @param  string $conditionName
     * @return string
     */
    protected function methodFromConditionName($conditionName)
    {
        return "condition".ucfirst($conditionName);
    }

    /**
     * Condición general disponible para todos los queries,para filtrar por id
     * de clave primaria.
     * NOTE: Actualmente implementado con relaciones de un solo campo.
     * TODO: Implementar para relaciones compuestas.
     *
     * @return array raw condition
     */
    protected function conditionElems()
    {
        $key = $this->modelClass::getPk();
        $tableName = $this->modelClass::tableName();

        //>>!!! CUIDADO CON array_flatten, que no es nativa de PHP

        // Se asegura que lleguen como lleguen los datos,
        // se vuelquen en un único array
        $elems = array_flatten(array_map(function($arg) {
            return is_array($arg) ? $arg : preg_split('/\D+/', $arg);
        }, func_get_args()));

        return ["$tableName.$key" => $elems];
    }

    /**
     * Retorna las etiquetas de las condiciones. Este método es útil para el uso
     * de condiciones en vistas, como por ejemplo formularios para la generación de
     * reportes. Este método se redefine en cada clase que lo utilice.
     *
     * @return array conditions labels (name => label)
     * @see generateConditionLabel()
     */
    public function conditionsLabels()
    {
        return [];
    }

    /**
     * Devuelve la etiqueta correspondiente a la condición especificada.
     *
     * @param  string $condition
     * @return string
     * @see generateConditionLabel()
     * @see conditionsLabels()
     */
    public function getConditionLabel($condition)
    {
        $labels = $this->conditionsLabels();
        $label = isset($labels[$condition]) ? $labels[$condition] : $this->generateConditionLabel($condition);

        return preg_replace('/\s\*$/', '', $label);
    }

    /**
     * Genera una etiqueta amigable para el usuario, basada en una condición dada.
     * Interpreta las notaciones camel case o snake case devolviendo las palabras
     * separadas capitalizadas.
     *
     * @param  string $name
     * @return string
     */
    public function generateConditionLabel($name)
    {
        return Inflector::camel2words($name, true);
    }

}