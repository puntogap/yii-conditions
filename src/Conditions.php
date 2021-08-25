<?php

namespace PuntoGAP\YiiConditions;

use yii\helpers\Inflector;
use MatiasMuller\MethodsStacks\StackableCall;
use PuntoGAP\YiiConditions\ConditionsHandler;

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
     * @return ActiveQuery|array|bool|string nextCallMethod constant
     */
	public function __callfromConditions($name, $args)
    {
        $token = static::getConditionsHandlerToken();
        return ConditionsHandler::handleCall($this, $name, $args, $token) 
            ?: $this->nextCallMethod();
    }

    public function whereCondition($condition, $options = [])
    {
        $token = static::getConditionsHandlerToken();
        return ConditionsHandler::handleCondition($this, $condition, $options, $token) 
            ?: $this->nextCallMethod();
    }

    public function andWhereCondition($condition, $options = [])
    {
        $options['combineWith'] = 'and';
        return $this->whereCondition($condition, $options);
    }

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
     * Returns the attribute labels.
     *
     * Attribute labels are mainly used for display purpose. For example, given an attribute
     * `firstName`, we can declare a label `First Name` which is more user-friendly and can
     * be displayed to end users.
     *
     * By default an attribute label is generated using [[generateAttributeLabel()]].
     * This method allows you to explicitly specify attribute labels.
     *
     * Note, in order to inherit labels defined in the parent class, a child class needs to
     * merge the parent labels with child labels using functions such as `array_merge()`.
     *
     * @return array attribute labels (name => label)
     * @see generateAttributeLabel()
     */
    public function conditionsLabels()
    {
        return [];
    }

    /**
     * Returns the text label for the specified attribute.
     * 
     * @param  string $attribute the attribute name
     * @return string the attribute label
     * @see generateAttributeLabel()
     * @see attributeLabels()
     */
    public function getConditionLabel($condition)
    {
        $labels = $this->conditionsLabels();
        $label = isset($labels[$condition]) ? $labels[$condition] : $this->generateConditionLabel($condition);

        return preg_replace('/\s\*$/', '', $label);
    }

    /**
     * Generates a user friendly attribute label based on the give attribute name.
     * This is done by replacing underscores, dashes and dots with blanks and
     * changing the first letter of each word to upper case.
     * For example, 'department_name' or 'DepartmentName' will generate 'Department Name'.
     * 
     * @param  string $name the column name
     * @return string the attribute label
     */
    public function generateConditionLabel($name)
    {
        return Inflector::camel2words($name, true);
    }

}