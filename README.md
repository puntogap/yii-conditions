# Yii Conditions

[Yii Conditions](https://github.com/puntogap/yii-conditions) es una extensión para la clase de ActiveQuery de [Yii 2](https://www.yiiframework.com/) que permite reutilizar condiciones de consultas pre-armadas de forma modular siguiendo el principio DRY.

## Tabla de contenidos

- [Instalación](#instalacion)
- [Uso](#uso)
	+ [Preparando el modelo de la consulta](#preparando-el-modelo-de-consulta)
	+ [Creando la primera consulta](#creando-la-primera-consulta)
- [Modificadores lógicos](#modificadores-logicos)
	+ [Modificadores lógicos desde la llamada al método](#modificadores-logicos-desde-la-llamada-al-metodo)
	+ [Modificadores lógicos encadenados desde la definición de las condiciones](#modificadores-logicos-encadenados-desde-la-definicion-de-las-condiciones)
- [Condiciones directas o de relación](#condiciones-directas-o-de-relacion)
	+ [Condiciones de relación](#condiciones-de-relacion)
		* [Relación pura](#relacion-pura)
		* [Relación con condiciones en el método](#relacion-con-condiciones-en-el-metodo)
		* [Relación con condiciones en el argumento](#relacion-con-condiciones-en-el-argumento)
		* [Modificadores lógicos de la llamada a la condición de relación](#modificadores-logicos-de-la-llamada-a-la-condicion-de-relacion)
		* [Relaciones compatibles con condiciones de relación](#relaciones-compatibles-con-condiciones-de-relacion)
- [Condicionales de evaluación de la expresión](#condicionales-de-evaluacion-de-la-expresion)
	+ [Condicional de evaluación "If"](#condicional-de-evaluacion-if)
	+ [Condicional de evaluación "Based on"](#condicional-de-evaluacion-based-on)
- [Pasaje de argumentos a las condiciones](#pasaje-de-argumentos-a-las-condiciones)
- [Modificador de resultado "Condition"](#modificador-de-resultado-condition)
- [Condiciones predefinidas incluídas](#condiciones-predefinidas-incluidas)
- [Etiquetas de condiciones](#etiquetas-de-condiciones)
- [Términos utilizados](#terminos-utilizados)
- [Observaciones](#observaciones)

## Instalación

La forma recomendada para la instalación es a través de [Composer](http://getcomposer.org/download/).

```
composer require puntogap/yii-conditions
```


## Uso

#### Preparando el modelo de consulta

Lo primero que se requiere para comenzar a trabajar con _Yii Conditions_ es un modelo que extiende de la clase
`yii\db\ActiveRecord`, y su finder configurado a una instancia que extienda de la clase `yii\db\ActiveQuery`. Esta última instancia es la que implementará la presente extensión. A modo de ejemplo, supongamos que tenemos una clase para el modelo de usuarios:

```php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use app\models\UsuarioQuery;

class Usuario extends ActiveRecord
{
	// ... 

    public static function find()
    {
        return Yii::createObject(UsuarioQuery::className(), [get_called_class()]);
    }

	// ... 
}
```

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;

class UsuarioQuery extends ActiveQuery
{
	// ... 
}
```

A continuación se incluye el trait a la clase de consultas:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use PuntoGAP\YiiConditions\Conditions;

class UsuarioQuery extends ActiveQuery
{
	use Conditions;

	// ... 
}
```

Con este agregado la clase ya está preparada para usar _Yii Conditions_.

#### Creando la primera condición.

Supongamos que en la clase de consultas original hay un método para consultar (o modificar la consulta) sobre los usuarios activos.

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;

class UsuarioQuery extends ActiveQuery
{
	// ... 

	public function activos()
	{
		return $this->andWhere(['activo' => 1]);
	}
	
	// ... 
}
```

Si definimos un método de la siguiente manera:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use PuntoGAP\YiiConditions\Conditions;

class UsuarioQuery extends ActiveQuery
{
	use Conditions;
	
	// ... 

	protected function conditionActivos()
	{
		return ['activo' => 1];
	}
	
	// ... 
}
```

El trait `Conditions` actúa de forma que al consultar `app\models\Usuario::find()->activos()->all()` el resultado con esta última implementación será equivalente al de la implementación anterior. Sin embargo, esta segunda implementación nos da ahora una serie de grandes ventajas que la primera implementación no puede ofrecernos, como veremos a continuación.

> _Yii Conditions_ implementa una **interfaz fluida**, de forma que pueden encadenarse las condiciones que sean necesarias combinándose entre sí.

## Modificadores lógicos

#### Modificadores lógicos desde la llamada al método

Una vez definida una condición cualquiera `conditionFoo()`, podemos invocar los siguientes métodos:

```php
Model::find()->foo()->all();  // and where [condition]
Model::find()->andFoo()->all(); // and where [condition] , equivalente al llamado anterior
Model::find()->orFoo()->all(); // or where [condition]
Model::find()->notFoo()->all(); // and where not ([condition])
Model::find()->andNotFoo()->all(); // and where not ([condition]) , equivalente al llamado anterior
Model::find()->orNotFoo()->all(); // or where not ([condition])
```

De esta manera ya podemos ver cómo se puede utilizar cada condición de una forma más versátil, sumado a que la interfaz fluida nos permite hacer diferentes combinaciones más complejas partiendo de condiciones básicas que no volverán a ser definidas otra vez. 

Siguiendo con el ejemplo de los usuarios, a continuación podemos ver cómo partiendo de combinar dos condiciones básicas, podemos consultar un resultado un poco más complejo:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use PuntoGAP\YiiConditions\Conditions;

class UsuarioQuery extends ActiveQuery
{
	use Conditions;
	
	protected function conditionTienenTelefono()
	{
		return ['not', ['telefono' => null]];
	}

	protected function conditionTienenEmail()
	{
		return ['not', ['email' => null]];
	}

}
```

```php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Usuario;

class UsuarioController extends Controller
{
	public function actionFoo()
	{
		$usuariosConContacto = Usuario::find()
			->tienenTelefono()
			->orTienenEmail()
			->all();

		$usuariosSinContacto = Usuario::find()
			->notTienenTelefono()
			->andNotTienenEmail()
			->all();
	}

}
```

#### Modificadores lógicos encadenados desde la definición de las condiciones

Del ejemplo anterior, podemos encapsular la consulta de usuarios con contacto y hacerla reutilizable mediante otro método en la clase de consultas de la siguiente manera:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use PuntoGAP\YiiConditions\Conditions;

class UsuarioQuery extends ActiveQuery
{
	use Conditions;
	
	protected function conditionTienenTelefono()
	{
		return ['not', ['telefono' => null]];
	}

	protected function conditionTienenEmail()
	{
		return ['not', ['email' => null]];
	}

	// Combina las dos condiciones anteriores
	protected function conditionTienenContacto()
	{
		return ['or',
			'tienenTelefono',
			'tienenEmail',
		];
	}

}
```

De esta manera, no sólo nos beneficia si queremos consultar los usuarios con contacto como en el ejemplo anterior, sino que también nos sirve automáticamente para obtener los usuarios sin contacto, como vemos en el siguiente ejemplo:

```php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Usuario;

class UsuarioController extends Controller
{
	public function actionFoo()
	{
		$usuariosConContacto = Usuario::find()->tienenContacto()->all();

		$usuariosSinContacto = Usuario::find()->notTienenContacto()->all();
	}

}
```

De esta forma nos olvidamos de la complejidad de tener que negar una combinación de condiciones, que puede dar lugar a errores. Asimismo nos abstraemos de la lógica involucrada en la consulta. Una consecuencia de esto es que, en caso que alguna condición, por ejemplo "tienenTelefono", se tenga que corregir implementándose de una forma diferente, entonces cambiando sólo la implementación de esta condición básica, todo el resto de las consultas serán corregidas automáticamente.

> Es importante tener en cuenta que al incluir condiciones dentro de las definiciones de otras condiciones, estas condiciones individuales incluidas de tipo `string` terminan en última instancia interpretándose como una condición de tipo `array` en la forma en que **Yii** reconoce. Por ejemplo, a continuación se muestra una forma correcta y una incorrecta de incluir una condición:

```php
	protected function conditionActivos()
	{
		return ['activo' => 1];
	}

	protected function conditionTienenTelefono()
	{
		return ['not', ['telefono' => null]];
	}

	protected function conditionInactivos()
	{
		// 'activos' es equivalente a ['activo' => 1]

		return ['not', ['activos']]; // Forma INCORRECTA, devuelve ERROR
									 // Es equivalente a ['not', [['activo' => 1]]]

		return ['not', 'activos'];   // Forma CORRECTA
									 // Es equivalente a ['not', ['activo' => 1]]
	}
```

> Es posible incluso por cuestiones de legibilidad o semántica, crear condiciones que sean alias de otras.

```php
	protected function conditionTienenTelefono()
	{
		return ['not', ['telefono' => null]];
	}

	protected function conditionConTelefono()
	{
		return 'tienenTelefono';
	}

	// ->tienenTelefono() y ->conTelefono() 
	// devolverán el mismo query
```

> Las condiciones pueden anidarse indefinidamente, siempre y cuando haya lógicamente un anidado coherente, sin bucles entre las dependencias de las condiciones.


## Condiciones directas o de relación

Hasta ahora hemos definido un tipo de condición, las **condiciones directas**, que aplican directamente sobre el modelo en cuestión y su entidad correspondiente en la base de datos _(independientemente de que se realicen joins, in(), y demás operadores, ver detalle en [Observaciones](#observaciones))_. Existe la posibilidad de construir consultas basadas en las relaciones definidas en los modelos ActiveRecord, ampliando muchísimo más las posibilidades de modularización y reutilización de las consultas definidas.

#### Condiciones de relación

Las **condiciones de relación** nos permiten, dada una relación definida en nuestro modelo ActiveQuery, poder utilizarla como parte de una consulta para condicionar el resultado de la consulta en base a la existencia o no de instancias relacionadas a través de dicha relación. Continuamos con el ejemplo de la clase `Usuario` a continuación para ver los diferentes tipos de condiciones de relación posibles.

```php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use app\models\UsuarioQuery;

class Usuario extends ActiveRecord
{
	// ...

    public static function find()
    {
        return Yii::createObject(UsuarioQuery::className(), [get_called_class()]);
    }

    public function getCiudad()
    {
        return $this->hasOne(Ciudad::class, ['id' => 'ciudad_id']);
    }

    public function getPosts()
    {
        return $this->hasMany(Post::class, ['usuario_id' => 'id']);
    }

	// ...
}

```

###### Relación pura

Consiste en consultar por la existencia de elementos de dicha relación tal y como fue definida.

```php
Foo::find()->withBar()->all();
```

En el ejemplo anterior de la clase `Usuario`, podemos consultar los usuarios que tienen localidad asignada, o que son propietarios de posts:

```php
use app\models\Usuario;

// Devuelve los usuarios con localidad asignada
Usuario::find()->withLocalidad()->all(); 

// Devuelve los usuarios propietarios de posts
Usuario::find()->withPosts()->all(); 
```

Como podemos ver, tantos las relaciones `hasOne` como `hasMany` son compatibles con las condiciones de relación _(ver las [relaciones compatibles](#relaciones-compatibles-con-condiciones-de-relacion) con condiciones de relación)_.

###### Relación con condiciones en el método

Añadiendo al llamado de la condición de relación pura una relación o sucesión de relaciones puras, siempre en formato _camel_case_, podemos también condicionar la propia relación a la que referimos en la consulta, conservando claridad y una fácil legibilidad en el código. 

```php
Foo::find()->withBarOneConditionAnotherCondition()->all();
```

Anteriormente veíamos como consultabamos a usuarios si tenían localidad asignada. Esta consulta es más que probable que sea redundante ya que lo más usual es que cada usuario tenga obligatoriamente una localidad asignada. Sin embargo, podríamos tener el siguiente escenario:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use PuntoGAP\YiiConditions\Conditions;

class LocalidadQuery extends ActiveQuery
{
	use Conditions;

	protected function conditionActivas()
	{
		return ['activa' => 1];
	}
```

Esta condición nos permite obtener las localidades "activas" mediante `Localidad::find()->activas()->all()` como ya hemos visto. Pero también nos da automáticamente la posibilidad de consultar los usuarios que pertenecen a localidades activas siguiendo el criterio mencionado anteriormente de encadenar al nombre de la relación, el nombre de la condición "activas" con la notación _camel_case_ de la siguiente manera:

```php
// Devuelve los usuarios pertenecientes a localidades activas
Usuario::find()->withLocalidadActivas()->all(); 
```

> `Localidad` es en singular porque es el nombre de la relación; y `Activas` es en plural porque corresponde a la forma en que está declarada la condición _(usualmente las condiciones como convención se declaran en plural)_.

> La obtención del nombre de relación y las subsecuentes condiciones desde el método invocado, es según primero/a encontrado/a.

###### Relación con condiciones en el argumento

Si necesitamos personalizar el condicionamiento de la relación por la que se está filtrando, puede pasarse dicha condición como primer argumento del llamado a la condición de relación de la siguiente forma:

```php
Foo::find()->withBar($rawCondition)->all();
```

La condición pasada por parámetro tiene la misma forma que usan los retornos de las definiciones de condiciones, o sea, una condición o array de combinación de condiciones.

```php
// Equivalente a la forma general expuesta en el tipo de condición anterior
Foo::find()->withBar(['and', 'oneCondition', 'anotherCondition'])->all();
```

Traduciendo el ejemplo anterior de las localidades a esta forma de condición, tenemos:

```php
// Devuelve los usuarios pertenecientes a localidades activas
Usuario::find()->withLocalidad('activas')->all(); 
```

Aunque justamente la ventaja de este tipo de condición es su capacidad para hacer condicionamientos más complejos, por ejemplo:

```php
// Devuelve los usuarios con posts activos o no eliminados
Usuario::find()->withPosts(['or', 
	'activos',
	['not', 'eliminados']
])->all(); 
```

###### Modificadores lógicos de la llamada a la condición de relación

Todas las llamadas a condiciones de relación admiten los mismos modificadores lógicos desde la llamada al método.

```
->[not]WithRelation...(...)
->[or|and][Not]WithRelation...(...)
```

Por ejemplo:
```php
Foo::find()->notWithBar()->all();
Foo::find()->andWithBarOneCondition()->all();
Foo::find()->orNotWithBar('anotherCondition')->all();
```

###### Relaciones compatibles con condiciones de relación

Las relaciones que pueden ser utilizadas pueden ser tanto ser simples como compuestas. Y en caso de ser compuestas, son compatibles tanto las que son construídas utilizando los métodos `via` como `viaTable`. Asimismo si las relaciones tienen alguna modificación en el query como un "andWhere", "andCondition" en su implementación, esta modificación será conservada.
La construcción de las consultas relacionadas no se realiza mediante _joins_, sino sobre _subqueries anidados_, tanto para evitar _conflictos con colisiones_ cuando las relaciones incluyen personalizaciones en sus implementaciones; como paraevitar el problema de duplicación de elementos en los casos de relaciones _hasMany_.


## Condicionales de evaluación de la expresión

A menudo es necesario aplicar una condición a una consulta únicamente si se cumple cierto criterio, o bien se quiere consultar por una condición sea de forma positiva o negativa en base a un parámetro, por ejemplo en una generación de reportes.

#### Condicional de evaluación "If"

Este condicional funciona agregando el sufijo "If" al nombre del método. Al hacer esto, el método espera recibir un argumento, y recibirá la condición dado el valor de veracidad de este parámetro. Si el parámetro retorna un valor equivalente a `true`, la condición se encadenará a la consulta, sino, se omitirá. 

```php
Model::find()->fooIf($condicion)
```

Por ejemplo, esta acción obtendrá los usuarios activos sólo si se solicita desde el request.

```php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Usuario;

class UsuarioController extends Controller
{
	public function actionListarUsuarios()
	{
		$filtrarActivos = Yii::$app->request->get('mostrar_activos');

		$usuarios = Usuario::find()->activosIf($filtrarActivos)->all();
	}

}
```

#### Condicional de evaluación "Based on"

Este condicional funciona agregando el sufijo "BasedOn" al nombre del método. Al igual que el condicional "If", espera recibir un argumento, pero a diferencia de este, el "Based On" devuelve la condición positiva o negativa según el valor de veracidad del argumento, y omite la condición si el argumento tiene valor nulo. 
Los valores interpretados como nulos son `null` y cadena vacía, o una cadena con sólo espacios. El resto de valores de valor de veracidad falso, como `false`, `0`, `[]` son interpretados como valor negativo y darán como resultado la negación de la condición.

```php
Model::find()->fooBasedOn($condicion)
```

En el siguiente ejemplo se ilustra el resultado de una consulta de usuarios según el parámetro recibido desde el request.

```php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Usuario;

class UsuarioController extends Controller
{
	public function actionListarUsuarios()
	{
		// Si llega por ejemplo '1', es de valor positivo
		// Si llega por ejemplo '0', es de valor negativo
		// Si llega por ejemplo '', es de valor es nulo
		$conTelefono = Yii::$app->request->get('con_telefono');

		// Devuelve usuarios "con teléfono" si es de valor positivo
		// Devuelve usuarios "sin teléfono" si es de valor negativo
		// Devuelve todos los usuarios si es de valor nulo
		$usuarios = Usuario::find()->tienenTelefonoBasedOn($conTelefono)->all();
	}

}
```

Todos los _[modificadores lógicos](#modificadores-logicos-desde-la-llamada-al-metodo)_ listados son compatibles con los _condicionales de evaluación_. Tanto las condiciones directas como de relación admiten estos condicionales. Estos son algunos ejemplos:

```php
Model::find()->andFooIf($condicion)->all();
Model::find()->orNotFooBasedOn($condicion)->all();
Foo::find()->notWithBarIf($condicion)->all();
Foo::find()->withBarOneConditionBasedOn($condicion)->all();
Foo::find()->withBarBasedOn($condicion, 'anotherCondition')->all();
```

> **Obs**: El primer argumento siempre será la condición para el condicional de evaluación. En el último ejemplo puede verse como el argumento que usa la condición de relación con condiciones en el argumento se desplaza al segundo lugar, dejando el primer lugar para la evaluación.


## Pasaje de argumentos a las condiciones

Lo más probable es que no tardemos demasiado en necesitar definir condiciones que dependan de ciertos parámetros, para lo que usualmente definiríamos una función que reciba los parámetros como argumentos. Las siguientes son especificaciones acerca del uso de argumentos en las definiciones de las condiciones y sus llamadas.

- Las condiciones directas pueden definirse con la cantidad de argumentos que sea necesaria. No hay restricción, y llamarse, se invocan tal cual fueron definidos.

```php
// Definición de la condición
protected function conditionFoo($param1, $param2) {
	// ...
}

// Llamado a la condición
Model::foo('val1', 'val2')->all();
```

- Se puede pasar valores de parámetros a las condiciones definidas con tales, también cuando forman parte como sub-condiciones, en la definición de otra condición. Se asignan luego del `:` y separados por `,`. 

```php
// Definición de condición dependiente de foo con parámetros fijos
protected function conditionBar() {
	return 'foo:val1,val2';
}

// Definición de condición dependiente de foo con un parámetro fijo y uno variable
protected function conditionBaz($param1) {
	// Nótese las comillas dobles para la interpolación de $param1
	return "foo:val1,$param1";
}

// Equivale a llamar Model::foo('val1', 'val2')->all();
Model::bar()->all();

// Equivale a llamar Model::foo('val1', 'val3')->all();
Model::baz('val3')->all();
```

- Las relaciones puras no llevan argumentos. Justamente el hecho que sean puras significa que no están condicionadas a ningún criterio adicional. La única posibilidad existente es utilizar un único argumento, y esto transforma automáticamente a la condición en una [relación con condiciones en el argumento](#relacion-con-condiciones-en-el-argumento).

- Las relaciones con condiciones en el método reciben la cantidad de argumentos que sea necesaria. Este o estos argumentos serán aplicados por igual a cada una de las condiciones. El uso de argumentos en este caso es útil principalmente para cuando se utiliza una única condicion, que es caso más habitual. Si necesitase argumentarse de forma diferente dos o más condiciones, siempre está la posibilidad de usar el array de condiciones, con los argumentos concatenados en las sub-condiciones seguidos de `:`, como se menciona anteriormente.

- Al aplicarse condicionales de evaluación "If" o "Based On", siempre se desplazan los argumentos una posición, dejando el primer lugar para el argumento del condicional de evaluación, como se explica [anteriormente](#condicionales-de-evaluacion-de-la-expresion).


## Modificador de resultado "Condition"

Si de una llamada a una condición deseamos obtener únicamente el `array` resultante interpretable por **Yii**, añadimos al final del nombre del método el sufijo `Condition`. Por ejemplo:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use PuntoGAP\YiiConditions\Conditions;

class UsuarioQuery extends ActiveQuery
{
	use Conditions;

	protected function conditionActivos()
	{
		return ['activo' => 1];
	}

	protected function conditionTienenTelefono()
	{
		return ['not', ['telefono' => null]];
	}

	protected function conditionEjemplo()
	{
		return ['and', 'activos', 'tienenTelefono'];
	}
```

```php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Usuario;

class UsuarioController extends Controller
{
	public function actionFoo()
	{
		// Ejemplo de la condición original
		$condicionPositiva = Usuario::find()->ejemploCondition();
		// Retorna ['and', ['activo' => 1], ['not', ['telefono' => null]]]
		
		// Ejemplo de la condición "negada"
		$condicionNegada = Usuario::find()->notEjemploCondition();
		// Retorna ['not', ['and', ['activo' => 1], ['not', ['telefono' => null]]]]

		// Cualquiera de las condiciones es compatible con ActiveQuery de Yii
		Usuario::find()->where($condicionPositiva)->all();
	}

}
```

Todos los _[modificadores lógicos](#modificadores-logicos-desde-la-llamada-al-metodo)_ listados así como los  _[condicionales de evaluación](#condicionales-de-evaluacion-de-la-expresion)_ son compatibles con el _modificador de resultado_. Tanto las condiciones directas como de relación lo admiten por igual. Estos son algunos ejemplos:

```php
Model::find()->andNotFooCondition();
Model::find()->orFooBasedOnCondition($condicion);
Foo::find()->withBarCondition()->all();
Foo::find()->orNotWithBarBasedOnCondition($condicion, [
	'and', 'oneCondition', 'anotherCondition',
])->all();
```


## Condiciones predefinidas incluídas

El paquete viene actualmente con una condición predefinida incluida en el trait Conditions, de forma que cada modelo de consulta que utilice este trait dispone de ella.

```php
->elems($ids)
```

Busca todos los registros de la clase tal que su clave primaria corresponda con el _id_ o _`array` de ids_ pasados por parámetro. Cumple con todas las especificaciones mencionadas en el documento. Funciona independientemente del nombre que tenga el campo de clave primaria. 
Algunos ejemplos:

```php
// Consulta por el usuario con id = 1
Usuario::find()->elems(1)->one(); // Equivalente a Usuario::findOne(1)

// Consulta por los usuarios que tienen los ids 1 o 2. $filtrar = true, entonces 
// se ejecuta la condición, y el argumento de los ids se desplaza al segundo lugar.
$filtrar = true;
Usuario::find()->notElemsIf($filtrar, [1, 2])->all();

// Consulta los usuarios que correspondan a la localidad con id 1 o 2.
Usuario::find()->withLocalidadElems([1, 2])->all();

// Construye la consulta de los usuarios que, o bien sean activos, o bien sean los
// usuarios con id 3 o 4. Retorna un array compatible con ->where() de ActiveQuery.
$condicion = Usuario::find()->activos()->orElemsCondition([3, 4]);
// Ejecuta la consulta
Usuario::find()->where($condicion)->all();
```









## Etiquetas de condiciones

El paquete también incluye un etiquetado de condiciones, análogo a los _"attribute labels"_ que implementa _ActiveRecord_, útil para cuando se construye una vista con una interfaz que usa  condiciones combinables a modo de filtros para realizar consultas personalizadas, por ejemplo para reportes.

```php
// Definición de las etiquetas dentro del modelo de consulta
public function conditionsLabels()
{
    return [
    	// ...
    ];
}

// Uso de las etiquetas
Model::find()->getConditionLabel($nombreCondicion);
```

Por ejemplo:

```php
namespace app\models;

use Yii;
use yii\db\ActiveQuery;

class UsuarioQuery extends ActiveQuery
{
	public function conditionsLabels()
	{
	    return [
	    	'activos' => 'Activos',
	    	'withLocalidadActivas' => 'Con localidad activa',
	    ];
	}
}
```

```php
Usuario::find()->getConditionLabel('activos'); 
// 'Activos'

Usuario::find()->getConditionLabel('withLocalidadActivas'); 
// 'Con localidad activa'

Usuario::find()->getConditionLabel('tienenTelefono'); 
// 'Tienen Telefono' (interpreta la notación "camel case" cuando no está definida)
```


## Términos utilizados

- _Raw conditions_: Una directiva string, o un array con directivas, que pueden ser tanto condiciones
  nativas de ActiveQuery como otras condiciones del tipo Condition.
- _Processed conditions_: un array que puede ser interpretado por ->where() de ActiveQuery de *Yii*.


## Observaciones

1. Se puede fácilmente reemplazar de a poco una implementación normal de _Yii_ con **Yii Conditions** únicamente incluyendo el Trait en el modelo de consulta y reemplazando una por una las funciones normales como `ejemplo()` por sus respectivas condiciones `conditionEjemplo()`, retornando el array que define la condición en lugar de el propio objeto de consulta.

2. Cuidado con definir la función `__call()` dentro del modelo de consulta. Esta librería usa `MatiasMuller\MethodsStacks\StackableCall`, así que en caso de que se haya definido, renombrarla por la forma `__callfromLoQueSea` y seguirá funcionando correctamente. En caso de necesitarse, para más detalles [ver la documentación correspondiente](https://gitlab.com/matiasmuller/methods-stacks).

3. Si se necesitara aplicar un orden, o un join, por ejemplo, se puede realizar en la propia definición de una condición, siempre y cuando lo que se retorne sea el array de la _"raw condition"_.

4. Para implementar **Yii Conditions** en todos los modelos, se recomienda crear una clase general que extienda de `yii\db\ActiveQuery`, e incluir el trait en esta nueva clase, para usarse como clase base para los modelos de consulta.
