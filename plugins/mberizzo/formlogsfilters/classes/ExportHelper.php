<?php namespace Mberizzo\FormLogsFilters\Classes;

use Illuminate\Support\Facades\DB;
use Mberizzo\FormLogsFilters\Models\Log;
use Mberizzo\FormLogsFilters\Traits\FormSettings;

class ExportHelper
{

    use FormSettings;

    protected $formId;
    protected $fields;

    public function __construct($formId)
    {
        $this->formId = $formId;
        $this->fields = $this->fields();
    }

    public function getExportableColumns()
    {
        $this->fields->each(function ($field) use (&$data) {
            if ($this->isNonExportable($field)) {
                return;
            }

            list($name, $label) = $this->makeColumn($field);

            $data[$name] = $label;
        });

        return $data;
    }

    private function makeColumn($field)
    {
        $name = "form_data.{$field->name}.value";

        if (in_array($field->name, ['id', 'created_at'])) {
            $name = $field->name;
        }

        return [$name, $field->label];
    }

    private function isNonExportable($field)
    {
        $type = $field->field_type->code;

        if (in_array($type, ['submit', 'recaptcha'])) {
            return true;
        }

        return false;
    }

    public function logQuery($data)
    {
        $query = Log::where('form_id', $this->formId);

        $query = $this->addDateScopeToLogQuery($query, $data);

        return $query;
    }

    private function addDateScopeToLogQuery($query, $data)
    {
        if ($data->date_from && $data->date_to) {
            return $query->whereBetween('created_at', [
                $data->date_from,
                $data->date_to
            ]);
        }

        return $query;
    }

    /**
     * Raw selection inside JSON column
     * @param string $col. i.e: form_data.name.value
     */
    public function getQuerySelect4JsonData($col)
    {
        if (! $this->isJsonColumn($col)) {
            return $col;
        }

        $xp = explode('.', $col);

        // i.e: form_data->>"$.name.value" as "form_data.name.value"
        $qs = $xp[0] . '->>"$.' . "{$xp[1]}.{$xp[2]}" . '" as "' . $col . '"';

        return DB::raw($qs);
    }

    private function isJsonColumn($column)
    {
        $parts = explode('.', $column);

        return count($parts) == 3;
    }
}