<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

/**
 * Модель для работы с типами сущностей.
 * Позволяет регистрировать типы сущностей
 * и получать информацию по ним 
 */
final class EntityTypes extends Model
{

    protected $allowTypes = [
        "string",
        "integer",
        "biginteger",
        "float",
        "double",
        "decimal",
        "boolean",
        "char",
        "text",
        "longtext",
        "binary",
        "blob",
        "date",
        "datetime",
        "time",
        "json",
        "uuid"
    ];


    public $timestamps = false;
    protected $fillable = [
        'entity_type_name',
        'entity_class_name',
        'fields_info_table',
        'entity_table_name'
    ];
    /**
     * Регистрирует тип сущности в системе.
     * В процесс регистрации сущности входит валидация полей сущностей, 
     * внесение данных о полях и типе сущности в таблицу,
     * создание миграции для сущности
     * @param string $typeName название типа сущности
     * @param string $class название класса, определяющего тип сущности с полным путем
     * @param array $fields массив полей сущности вида ["имя поля" => ["тип поля", $otherParam, ...]].
     * В некоторых типах требуется несколько параметров, например в типе CHAR требуется второй параметр,
     * который указывает на размер типа
     * @return int идентификатор зарегистрированного типа сущности
     */
    public function registerType(string $typeName, string $class, array $fields): int {
        if($this->checkTypeName($typeName) && $this->checkTypeClass($class)){
            $fieldsInfoTableName = $this->createEntityFieldsInfoTable($typeName, $fields);
            $etn = $this->createEntityMigration($typeName, $fields);
            return self::create(["entity_type_name" => $typeName, "entity_class_name" => $class, "fields_info_table" => $fieldsInfoTableName, "entity_table_name" => $etn])->id;
        }
    }

    /**
     * Получает информацию о зарегистрированном типе сущности
     * @param int $typeId идентификатор зарегистрированного типа сущности
     * @return array массив с информацией о типе
     */
    public function getTypeInfo(int $typeId): array {
        if($this->checkEntityTypeExists($typeId)){
            $typeInfoResArr = [];
            $typeInfo = $this->where("id", $typeId)->first();
            $typeInfoResArr['ENTITY_TYPE_ID'] = $typeId;
            $typeInfoResArr['ENTITY_TYPE_NAME'] = $typeInfo->entity_type_name;
            $typeInfoResArr['ENTITY_CLASS_NAME'] = $typeInfo->entity_class_name;
            $typeInfoResArr['ENTITY_TABLE_NAME'] = $typeInfo->entity_table_name;
            $typeInfoResArr['ENTITY_FIELDS_INFO_TABLE_NAME'] = $typeInfo->fields_info_table;
            $fieldsInfo = DB::table($typeInfo->fields_info_table)->get();
            $fieldsArr = [];
            foreach($fieldsInfo as $field){
                $fieldsArr[$field->field_name] = json_decode($field->field_type, true);
            }
            $typeInfoResArr['ENTITY_FIELDS_INFO'] = $fieldsArr;
            return $typeInfoResArr;
        }
    }

    /**
     * Проверяет существование поля в типе
     * @param int $typeId идентификатор зарегистрированного типа сущности
     * @param string $fieldName название поля в сущности
     * @return bool возвращает true, если поле существует в сущности
     * @throws Exception выбрасывает исключение если поля не существует в сущности
     */
    public function checkFieldExists(int $typeId, string $fieldName): bool {
        $info = $this->getTypeInfo($typeId);
        foreach($info['ENTITY_FIELDS_INFO'] as $lfieldName => $fieldInfo){
            if($lfieldName == $fieldName) return true;
        }
        throw new \Exception("The \"$fieldName\" field does not exist in the \"{$info['ENTITY_TYPE_NAME']}\" entity");
    }

    /**
     * Получает информацию по всем зарегистрированным типам в системе
     * Пока под вопросом, нужен ли этот метод
     */
    /*public function getAllEntityTypesInfo(): array {
        $rArr = [];
        $ids = $this->all("id");
        foreach($ids as $id) $rArr[] = $this->getTypeInfo($id->id);
        return $rArr;
    }*/

    /**
     * Проверяет то что имя типа является уникальным
     * @param string $typeName имя типа
     * @return bool возвращает true, если имя типа уникально
     * @throws Exception выбрасывает исключение если тип сущности с переданным именем уже существует
     */
    public function checkTypeName(string $typeName): bool {
        if(!self::where("entity_type_name", $typeName)->exists()) return true;
        else throw new \Exception("An entity of the \"$typeName\" type already exists");
    }

    /**
     * Проверяет то что имя класса типа является уникальным
     * @param string $class название класса, определяющего тип сущности с полным путем
     * @return bool возвращает true, если класса типа уникально
     * @throws Exception выбрасывает исключение если тип сущности с переданным именем класса уже существует
     */
    public function checkTypeClass(string $class): bool {
        if(!self::where("entity_class_name", $class)->exists()) return true;
        else throw new \Exception("An entity named \"$class\" class already exists");
    }

    /**
     * Проверяет то что переданный тип сущности зарегестрирован
     * @param int $typeId идентификатор типа сущности
     * @return bool возвращает true, если переданный идентификатор зарегистрирован
     * @throws Exception выбрасывает исключение если переданный идентификатор не зарегистрирован
     */
    public function checkEntityTypeExists(int $typeId): bool {
        if(self::where("id", $typeId)->exists()) return true;
        else throw new \Exception("The entity type with ID $typeId was not found");
    }

    

    /**
     * Функция создает таблицу и вносит в неё данные с информацией о полях таблиц сущностей.
     * Функция создает миграцию через командный интерфейс artisan, а затем применяет миграцию,
     * после чего через стандартный класс DB в созданную таблицу вносятся данные
     * @param string $typeName имя типа
     * @param array $fields массив полей сущности вида ["имя поля" => ["тип поля"]]
     * @return string возвращает имя созданной таблицы с информацией о полях сущности
     * @throws Exception выбрасывает исключение если происходят ошибки при вызове artisan
     */
    private function createEntityFieldsInfoTable(string $typeName, array $fields): string {
        //php artisan make:model bbb -m | grep -Po "Migration \[\K(.*?)(?=\])"
        $output = null;
        $modelName = "{$typeName}Fields";
        $artisanCode = Artisan::call("make:migration", ["name" => $modelName]);
        $migrationFilePath = trim(preg_replace('/.*\[(.*)\].*/', '$1', Artisan::output()));
        if($artisanCode !== 0) throw new \Exception("The system failed to register the type, an error occurred when executing 'artisan' - ".Artisan::output());
        $entityFieldInfoTableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
        $migrationFile = "<?php  use Illuminate\Database\Migrations\Migration;  use Illuminate\Database\Schema\Blueprint;  use Illuminate\Support\Facades\Schema;  return new class extends Migration  {  public function up(): void  {  Schema::create('$entityFieldInfoTableName', function (Blueprint \$table) {  \$table->id();  \$table->string('field_name');  \$table->string('field_type');  });  }  public function down(): void  {  Schema::dropIfExists('$entityFieldInfoTableName');  }  };  ?>";
        file_put_contents(__DIR__."/../../$migrationFilePath", $migrationFile);
        $artisanCode = Artisan::call("migrate --path=$migrationFilePath");
        if($artisanCode !== 0) throw new \Exception("The system failed to register the type, an error occurred when executing 'artisan' - ".Artisan::output());
        $this->addFieldsInfo($entityFieldInfoTableName, $fields);
        return $entityFieldInfoTableName;
    }

    /**
     * Функция проверяет, допустим ли указанный тип поля
     * @param string $sqlType тип, который необходимо проверить
     * @return true возвращает true если тип поддерживается
     * @throws Exception выбрасывает исключение если тип не поддерживается
     */
    private function checkSqlType(string $sqlType): true {
        //https://codeease.net/programming/php/laravel-table-data-types
        $sqlType = strtolower($sqlType);
        if (in_array($sqlType, $this->allowTypes)){
            return true;
        } else throw new \Exception("The $sqlType type is not supported");
    }

    /**
     * Непосредственно добавляет информацию о полях сущности в таблицу.
     * Внутри метода проверяется валидность типов полей. 
     * Названия столбцов и типов будут преобразованы в верхний регистр
     * @param string $tableName имя таблицы
     * @param array $fields массив информации о полях
     */
    private function addFieldsInfo(string $tableName, array $fields): void {
        //https://www.mousedc.ru/learning/425-dobavlenie-obnovlenie-udalenie-baza-dannyh-laravel/
        foreach($fields as $fieldName => $fieldType){
            if($this->checkSqlType($fieldType[0])){
                DB::table($tableName)->insert([
                    'field_name' => $fieldName, 
                    'field_type' => json_encode($fieldType)
                ]);
            }
        }
    }

    /**
     * Функция создает миграцию для типа сущностей
     * @param string $typeName название типа сущности
     * @param array $fields массив полей сущности вида ["имя поля" => ["тип поля", $otherParam, ...]].
     * @return string возвращает имя таблицы сущности
     * @throws Exception выбрасывает исключение если происходят ошибки при вызове artisan
     */
    private function createEntityMigration(string $typeName, array $fields): string {
        $entityTableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $typeName));
        $migrationScript = '$table->id(); ';
        foreach($fields as $fieldName => $fieldType){
            if($this->checkSqlType($fieldType[0])){
                $sqlType = strtoupper($fieldType[0]);
                if (in_array($sqlType, $this->allowTypes)){
                    switch ($sqlType){
                        case "biginteger":
                            $migrationScript .= "\$table->bigInteger('$fieldName'); ";
                            break;
                        case "char":
                            $charSize = 16;
                            if(isset($fieldType[1])) $charSize = $fieldType[1];
                            $migrationScript .= "\$table->char('$fieldName', $charSize); ";
                            break;

                        case "longtext":
                            $migrationScript .= "\$table->longtext('$fieldName'); ";
                            break;

                        case "dateTime":
                            $migrationScript .= "\$table->dateTime('$fieldName'); ";
                            break;
                        default:
                            $migrationScript .= "\$table->$sqlType ('$fieldName'); ";
                            break;
                    }
                }
            }
        }
        $migrationFile = "<?php  use Illuminate\Database\Migrations\Migration;  use Illuminate\Database\Schema\Blueprint;  use Illuminate\Support\Facades\Schema;  return new class extends Migration  {  public function up(): void  {  Schema::create('$entityTableName', function (Blueprint \$table) { $migrationScript });  }  public function down(): void  {  Schema::dropIfExists('$entityTableName');  }  };  ?>";
        $artisanCode = Artisan::call("make:migration", ["name" => $typeName]);
        $migrationFilePath = trim(preg_replace('/.*\[(.*)\].*/', '$1', Artisan::output()));
        if($artisanCode !== 0) throw new \Exception("The system failed to register the type, an error occurred when executing 'artisan' - ".Artisan::output());
        file_put_contents(__DIR__."/../../$migrationFilePath", $migrationFile);
        $artisanCode = Artisan::call("migrate --path=$migrationFilePath");
        if($artisanCode !== 0) throw new \Exception("The system failed to register the type, an error occurred when executing 'artisan' - ".Artisan::output());
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $typeName));;
    }

}
