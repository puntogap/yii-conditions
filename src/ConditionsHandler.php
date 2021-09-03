<?php
/**
 * @copyright Copyright (c) 2020-2021 PuntoGAP
 * @link http://puntogap.com/
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace PuntoGAP\YiiConditions;

use yii\db\ActiveQuery;

/**
 * ConditionsHandler es la clase que se encarga de gestionar las condiciones y sus
 * métodos virtuales correspondientes.
 *
 * @author Matías Müller <matias.muller@puntogap.com>
 */
class ConditionsHandler
{
    /**
     * La expresión regular con la que se evalúa la llamada del método.
     * TODO: Discriminar "with" para poder procesar más prolijamente.
     *
     * @var string regex
     */
    protected $regexp = '/^(?:(?:(and|or)(Not)?|(not))([A-Z]\w+?)|([a-z]\w+?))(If|BasedOn)?(Condition)?$/';

    /**
     * El nombre del método llamado.
     *
     * @var string
     */
    protected $virtualMethod;

    /**
     * Los argumentos del método llamado.
     *
     * @var array
     */
    protected $args;

    /**
     * El token que da permiso a esta clase a comunicarse con el ActiveQuery.
     *
     * @var string
     */
    protected $token;

    /**
     * Modificador de condición. Indica si la condición resultante será
     * procesada por `andWhere` o `orWhere` respectivamente.
     *
     * @var string 'and'|'or'
     */
    protected $combineWith;

    /**
     * Modificador de condición. Indica si la condición resultante será negada o no.
     *
     * @var bool
     */
    protected $not;

    /**
     * Nombre de la condición base sin los modificadores adicionales.
     *
     * @var string
     */
    protected $baseCondition;

    /**
     * Evaluador de condición. Indica si la condición va a procesarse, según el
     * método de evaluación especificado, que puede ser "If" o "BasedOn".
     *
     * @var bool
     */
    protected $evaluation;

    /**
     * Indica si el resultado final será devuelto como ActiveQuery o sólo como una
     * "processed condition".
     *
     * @var bool
     */
    protected $returnsConditionOnly;

    /**
     * Indica si la condición base se evalúa como una condición directa del
     * ActiveQuery, o como una condición relacionada.
     *
     * @var bool
     */
    protected $isRelatedCondition;

    /**
     * Inicializa la instancia
     *
     * @param  ActiveQuery $query
     * @param  string  $virtualMethod
     * @param  array   $config
     * @return void
     */
    public function __construct(ActiveQuery $query, string $token, array $config = [])
    {
        $this->query = $query;
        // TODO: Validar que el token al menos exista
        $this->token = $token;

        // TODO: Validar los distintos escenarios posibles de opciones,
        // y arrojar una excepción en caso que no sean escenarios válidos.
        foreach ($config as $option => $value) {
            $this->$option = $value;
        }
    }

    /**
     * Atajo para procesar la llamada a la condición sin necesidad de invocar
     * la creación de instancia.
     *
     * @param  ActiveQuery $query
     * @param  string  $virtualMethod
     * @param  array   $args
     * @param  string  $token
     * @return ActiveQuery|array|bool
     */
    public static function handleCall($query, $virtualMethod, $args, $token)
    {
        $conditionHandler = new self($query, $token, compact('virtualMethod', 'args'));
        return $conditionHandler->handleConditionCall();
    }

    /**
     * Atajo para procesar una "raw condition" directamente, sin necesidad de
     * invocar la creación de instancia.
     *
     * @param  ActiveQuery  $query
     * @param  array|string $rawCondition
     * @param  array  $options
     * @param  string $token
     * @return ActiveQuery|array|bool
     */
    public static function handleCondition($query, $rawCondition, $options, $token)
    {
        $conditionHandler = new self($query, $token, $options);
        return $conditionHandler->handleRawCondition($rawCondition);
    }

    /**
     * Procesa la llamada a la condición. Si el método concuerda con una expresión
     * que pueda corresponder a una condición, se aplican los evaluadores de la
     * condición, y del resultado de la evaluación, continua el procesamiento.
     *
     * @return ActiveQuery|array|bool
     */
    public function handleConditionCall()
    {
        if ($this->parseConditionCall()) {
            // Aplica el evaluador si es que fue especificado
            if ($this->evaluation) {
                $evaluationMethod = "evaluate$this->evaluation";
                // Si la evaluación retorna valor falso, corta el procesamiento de
                // la condición, pero retorna el query original intacto.
                if (!$this->$evaluationMethod()) {
                    return $this->query;
                }
            }

            // Render de condición directa / relacionada.
            $conditionTypeMethod = $this->isRelatedCondition
                ? 'renderRelatedCondition'
                : 'renderDirectCondition';

            // Si el render no devuelve nada, se asume que no existe.
            if ($renderedQuery = $this->$conditionTypeMethod()) {
                return $renderedQuery;
            }
        }
        return false;
    }

    /**
     * Hace un parseo de la llamada de método. Si corresponde con la forma de una
     * condición, lee los datos para completar los atributos de la instancia.
     * Retorna si el parseo ha sido correcto.
     *
     * @return bool
     */
    protected function parseConditionCall()
    {
        if ($hasConditionFormat = preg_match($this->regexp, $this->virtualMethod, $match)) {
            $this->combineWith = $match[1] === 'or' ? 'or' : 'and';
            $this->not = $match[2] || $match[3];
            $this->baseCondition = lcfirst($match[4] ?: $match[5]);
            $this->evaluation = $match[6] ?? false;
            $this->returnsConditionOnly = !empty($match[7]);
            $this->isRelatedCondition = preg_match('/^[wW]ith/', $this->baseCondition);
        }
        return $hasConditionFormat;
    }

    /**
     * Evalúa el valor de entrada y devuelve uno los tres posibles valores de
     * significancia, `true`, `false` o `null`. `true` o `false` son valores con
     * significancia, pero positiva o negativa respectivamente. `null` significa
     * un valor sin significancia.
     * NOTE: Este podría ser un helper general.
     *
     * @param  mixed $input
     * @return bool|null Valor de significancia
     */
    protected function significance($input)
    {
        if (is_string($input)) {
            $input = trim($input);
        }
        if (is_numeric($input)) {
            $input = (float)($input);
        }
        return !in_array($input, ['', null], true)
            ? !!$input : null;
    }

    /**
     * Retorna verdadero si el primer parámetro pasado tiene un valor booleano
     * positivo, o si la evaluación no aplica, para no interrumpir el procesamiento.
     *
     * @return bool
     */
    protected function evaluateIf()
    {
        // El primer argumento es el condicionante.
        if ($args = $this->args) {
            $evaluateArg = array_shift($args);
            $this->args = $args;
        }

        return $this->significance($evaluateArg ?? null);
    }

    /**
     * Retorna verdadero si el primer parámetro pasado tiene un valor booleano
     * significativo, o si la evaluación no aplica, para no interrumpir el
     * procesamiento. Retorna falso si el argumento no es significativo, es decir,
     * nulo o cadena vacía.
     * Si el argumento tiene valor falso, se invierte "signo" de la condición.
     *
     * @return bool
     */
    protected function evaluateBasedOn()
    {
        // El primer argumento es el condicionante.
        if ($args = $this->args) {
            $evaluateArg = array_shift($args);
            $this->args = $args;
        }

        $argValue = $this->significance($evaluateArg ?? null);

        if (is_null($argValue)) {
            return false;
        }
        if (!$argValue) {
            $this->not = !$this->not;
        }
        return true;
    }

    /**
     * Renderiza la condición, basada en una "processed condition", que termina de
     * completar según los parámetros de la instancia "not"y "and/or", y devuelve
     * en formato de "processed condition" o de ActiveQuery según el parámetro de la
     * instancia "returnsConditionOnly".
     *
     * @param  array $processedCondition processed condition
     * @return ActiveQuery|array processed condition
     */
    protected function renderCondition($processedCondition)
    {
        $condition = $this->not
            ? ['not', $processedCondition]
            : $processedCondition;

        if ($this->returnsConditionOnly) {
            return $condition;
        } else {
            $method = $this->combineWith . 'Where';
            return $this->query->$method($condition);
        }
    }

    /**
     * Procesa la condición cuando es directa del ActiveQuery $query. Si la
     * condición no existe, retorna null.
     *
     * @return ActiveQuery|array|null
     */
    protected function renderDirectCondition()
    {
        if ($this->query->existsCondition($this->baseCondition)) {
            $rawCondition = $this->query->getRawCondition(
                $this->baseCondition,
                $this->args,
                $this->token
            );
            $processedCondition = $this->processCondition($rawCondition);
            return $this->renderCondition($processedCondition);
        }
        return null;
    }

    /**
     * Procesa la condición cuando es relacionada al ActiveQuery $query. Si la
     * condición no existe, retorna null.
     *
     * @return ActiveQuery|array|null
     */
    protected function renderRelatedCondition()
    {
        // Parseo de método base
        $splittedWords = $this->splitRelationFromConditionsWords();

        if ($relationName = $splittedWords->relationName)
        {
            // Prepara la relación y la condición resultante que la vincula
            $preparedResult = $this->prepareRelationCondition($relationName);

            // Posterior aplicación de condiciones a la relación
            $this->applyConditionsToRelation(
                $preparedResult->relation,
                $splittedWords->conditionsWords
            );

            return $this->renderCondition($preparedResult->processedCondition);
        }
        return null;
    }

    /**
     * aplicación de condiciones a la relación según haya llegado con palabras de
     * condiciones o no.
     *
     * @param  ActiveRecord $subQuery
     * @param  array $words
     * @return void
     */
    protected function applyConditionsToRelation($subQuery, $words)
    {
        // Si tiene "words" (posibles condiciones de la relación)
        if ($words) {
            // Esta modalidad aprovecha el pasaje de argumentos a las condiciones
            if ($conditions = static::parseConditionsFromWords($subQuery, $words)) {
                // Aplica todas las condiciones al query de la relación
                foreach ($conditions as $condition) {
                    // Llama el metodo "virtual"
                    // TODO: Ver si se puede dar una solución a la entrada de
                    // argumentos a la función whereCondition para hacer uso de
                    // ésta en esta parte en lugar de llamar al método virtual.
                    call_user_func_array([$subQuery, $condition], $this->args);
                }
            }
        }
        // Si no tiene condiciones de la relación en el método, entonces toma como
        // condiciones el primer argumento, que puede ser una condición o array de
        // condiciones.
        else {
            // TODO: Implementar una excepción si se pasa un segundo argumento.
            if ($this->args) {
                $condition = $this->args[0];
                $subQuery->andWhereCondition($condition);
            }
        }
    }

    /**
     * Procesa la condición base para separar la relación correspondiente, de las
     * palabras que actuarán como posibles condiciones de la relación.
     * TODO: Dar la posibilidad de hallar el nombre de la relación al final de la
     * cadena, para hacer compatible con idioma inglés.
     *
     * @return stdClass ['relationName' => string, 'conditionsWords' => array]
     */
    protected function splitRelationFromConditionsWords()
    {
        // TODO: Cuidado, obtiene la relación, pero no reagrupa las "words" en condiciones.
        // Estaría bueno que haga eso también, porque la lógica queda separada sino

        // Obtiene todas las palabras de la condición base.
        preg_match('/^[wW]ith(.*)$/', $this->baseCondition, $match);
        $words = array_filter(explode('|', preg_replace('/([A-Z])/', "|$1", $match[1])));

        // Busca las ocurrencias más cortas, porque se trataría de la relación
        // más "básica".
        $relationName = $conditionsWords = null;
        $n = count($words);
        $i = 1;
        while ($i <= $n && !$relationName) {
            $ucRelationName = implode('', array_slice($words, 0, $i));
            $relationFn = "get$ucRelationName";
            if (method_exists($this->query->modelClass, $relationFn)) {
                $relationName = lcfirst($ucRelationName);
                $conditionsWords = array_slice($words, $i);
            } else {
                $i++;
            }
        }

        return (object)compact([
            'relationName',
            'conditionsWords',
        ]);
    }

    /**
     * Extrae todas las condiciones, priorizando las "primeras encontradas"
     * Se asegura de que todas las palabras sean encontradas como
     * condiciones. No puede haber un "resto" sin ser encontrado
     * como condición.
     *
     * @param  ActiveQuery $subQuery
     * @param  array  $words
     * @return array|null
     */
    protected static function parseConditionsFromWords($subQuery, $words)
    {
        $wordChain = '';
        $conditions = [];
        foreach ($words as $word) {
            $wordChain .= $word;
            $possibleCondition = lcfirst($wordChain);
            if ($subQuery->existsCondition($possibleCondition)) {
                $conditions[] = $possibleCondition;
                $wordChain = '';
            }
        }
        return !$wordChain ? $conditions : null;
    }

    /**
     * Obtiene la info de la relación y realiza el join correspondiente
     * TODO: Desambiguar la funcionalidad de este método, desde lo que internamente
     * hasta lo que devuelve.
     *
     * @return stdClass ['relation' => ActiveRecord, 'processedCondition' => array]
     */
    protected function prepareRelationCondition($relationName)
    {
        $relationFn = 'get'.ucfirst($relationName);
        $relation = (new $this->query->modelClass)->$relationFn();

        return $relation->via
            ? $this->compoundRelation($relation)
            : $this->simpleRelation($relation);
    }


    /**
     * Prepara la sub-consulta relacionada en caso que se tratase de una relación
     * simple y devuelve además la condición que se aplica para relacionar esta
     * sub-consulta a la consulta principal.
     *
     * @return stdClass ['relation' => ActiveRecord, 'processedCondition' => array]
     */
    protected function simpleRelation($relation)
    {
        // Campos (foraneo => local)
        $link = $relation->link;
        $foreign = array_keys($link)[0];
        $local = array_values($link)[0];
        // Tablas
        $foreignTable = $relation->modelClass::tableName();
        $localTable = $relation->primaryModel->tableName();

        // Quito la vinculación específica del modelo
        $relation->primaryModel = null;

        // "Tablas.Campos" para las vinculaciones
        $tLocal = "`$localTable`.`$local`";
        $tForeign = "`$foreignTable`.`$foreign`";

        $processedCondition = [
            $tLocal => $relation->select($tForeign),
        ];

        // Este refuerzo sirve para ayudar a la performance
        // TODO: Confirmar mediante una consistente revisión que no traiga
        // inconsistencias a la hora de anidar una tabla "sandwich", es decir: 
        // "tabla1 > tabla2 > tabla1"
        $relation->andWhere("$tLocal = $tForeign");

        // Devuelve la relación y la condición
        return (object)compact('relation', 'processedCondition');
    }

    /**
     * Prepara la sub-consulta relacionada en caso que se tratase de una relación
     * compuesta y devuelve además la condición que se aplica para relacionar esta
     * sub-consulta a la consulta principal.
     *
     * @return stdClass ['relation' => ActiveRecord, 'processedCondition' => array]
     */
    protected function compoundRelation($relation)
    {
        $via = $relation->via;
        $viaRelation = is_array($via);

        // Campos remotos (foraneo => local)
        $remoteLink = $relation->link;
        $remoteForeign = array_keys($remoteLink)[0];
        $remoteLocal = array_values($remoteLink)[0];
        // Tablas
        $remoteForeignTable = $relation->modelClass::tableName();
        $localTable = $relation->primaryModel->tableName();
        // Quito la vinculación específica del modelo
        $relation->primaryModel = null;

        // Query intermedio
        $pivot = $viaRelation ? $via[1] : $via;
        // Campos (foraneo => local)
        $link = $pivot->link;
        // Quito la vinculación específica del modelo
        $pivot->primaryModel = null;
        // Campos faltantes
        $foreign = array_keys($link)[0];
        $local = array_values($link)[0];
        // Tabla faltante
        $pivotTable = $viaRelation
            // Si es mediante ->via()
            ? $pivot->modelClass::tableName()
            // Si es mediante ->viaTable(), el valor anterior da la tabla local
            // Y el valor esperado está en ->from
            : $pivot->from[0];
        // Quito la vinculación específica del modelo
        $pivot->primaryModel = null;

        // "Tablas.Campos" para las vinculaciones
        $tLocal = "`$localTable`.`$local`";
        $tForeign = "`$pivotTable`.`$foreign`";
        $tRemoteLocal = "`$pivotTable`.`$remoteLocal`";
        $tRemoteForeign = "`$remoteForeignTable`.`$remoteForeign`";

        // Agrego la condición
        $pivot->andWhere([
            $tRemoteLocal => $relation->select($tRemoteForeign)
        ]);

        $processedCondition = [
            $tLocal => $pivot->select($tForeign)
        ];

        // Este refuerzo sirve para ayudar a la performance
        // TODO: Confirmar mediante una consistente revisión que no traiga
        // inconsistencias a la hora de anidar una tabla "sandwich", es decir: 
        // "tabla1 > tabla2 > tabla1"
        $relation->andWhere("$tRemoteLocal = $tRemoteForeign");
        $pivot->andWhere("$tLocal = $tForeign");

        // Devuelve la relación y la condición
        return (object)compact('relation', 'processedCondition');
    }

    /**
     * Procesa una "raw condition" directamente y devuelve el render de la condición
     * procesada.
     *
     * @param  array|string $rawCondition
     * @return ActiveQuery|array|bool
     */
    protected function handleRawCondition($rawCondition)
    {
        $processedCondition = $this->processCondition($rawCondition);
        return $this->renderCondition($processedCondition);
    }

    /**
     * Recibe una "raw condition" y devuelte la "processed condition"
     * correspondiente procesada recursivamente.
     *
     * @param  array|string $rawCondition
     * @return array
     */
    protected function processCondition($rawCondition)
    {
        if (is_array($rawCondition)) {
            return array_map(function($elem) {
                return $this->processCondition($elem);
            }, $rawCondition);
        }

        $processedElem = $this->processConditionElem($rawCondition);
        return $rawCondition !== $processedElem
            ? $this->processCondition($processedElem)
            : $processedElem;
    }

    /**
     * Realiza el procesamiento de una condición individual.
     *
     * @param  string $elem
     * @return array|string
     */
    protected function processConditionElem($elem)
    {
        if (is_string($elem)) {
            // Parseo, separación entre condición y argumentos.
            preg_match('/(\w+)(?:\:(.*))?/', $elem, $match);
            $conditionName = $match[1] ?? $elem;
            $args = isset($match[2]) ? explode(',', $match[2]) : [];

            // Caso de condición directa existente.
            if ($this->query->existsCondition($conditionName)) {
                return $this->query->getRawCondition($conditionName, $args, $this->token);
            }
            // Caso de condición relacionada.
            // NOTE: Esta evaluación por "with" está hecha para no tener problemas
            // con otros valores que dan problemas como "and", "or", etc... Sabiendo
            // que estos casos son los únicos posibles que no corresponden a
            // condiciones directas existentes.
            // TODO: Mejorar este problema de la evaluación por "with".
            elseif (preg_match('/^with([A-Z]\w+)$/', $conditionName)) {
                $conditionMethod = $conditionName.'Condition';
                // TODO: Ver si se puede dar una solución a la entrada de argumentos
                // a la función whereCondition para hacer uso de ésta en esta parte.
                return call_user_func_array([$this->query, $conditionMethod], $args);
            }
        }
        return $elem;
    }

}