<?php

namespace Yajra\DataTables\Processors;

use Illuminate\Support\Arr;
use Yajra\DataTables\Utilities\Helper;

class DataProcessor
{
    /**
     * @var int
     */
    protected $start;

    /**
     * Columns to escape value.
     *
     * @var array
     */
    protected $escapeColumns = [];

    /**
     * Processed data output.
     *
     * @var array
     */
    protected $output = [];

    /**
     * @var array
     */
    protected $appendColumns = [];

    /**
     * @var array
     */
    protected $editColumns = [];

    /**
     * @var array
     */
    protected $excessColumns = [];

    /**
     * @var mixed
     */
    protected $results;

    /**
     * @var array
     */
    protected $templates;

    /**
     * @var bool
     */
    protected $includeIndex;

    /**
     * @var array
     */
    protected $rawColumns;

    /**
     * @param mixed $results
     * @param array $columnDef
     * @param array $templates
     * @param int   $start
     */
    public function __construct($results, array $columnDef, array $templates, $start)
    {
        $this->results       = $results;
        $this->appendColumns = $columnDef['append'];
        $this->editColumns   = $columnDef['edit'];
        $this->excessColumns = $columnDef['excess'];
        $this->escapeColumns = $columnDef['escape'];
        $this->includeIndex  = $columnDef['index'];
        $this->rawColumns    = $columnDef['raw'];
        $this->templates     = $templates;
        $this->start         = $start;
    }

    /**
     * Process data to output on browser.
     *
     * @param bool $object
     * @return array
     */
    public function process($object = false)
    {
        $this->output = [];
        $indexColumn  = config('datatables.index_column', 'DT_Row_Index');

        foreach ($this->results as $row) {
            $data  = $this->escapeColumns(Helper::convertToArray($row));
            $value = $this->addColumns($data, $row);
            $value = $this->addIndexColumn($value, $row);
            $value = $this->editColumns($value, $row);
            $value = $this->setupRowVariables($value, $row);
            $value = $this->removeExcessColumns($value);
            $this->output[] = $object ? $value : $this->flatten($value);
        }

        return $this->output;
    }

    /**
     * Process add columns.
     *
     * @param mixed $data
     * @param mixed $row
     * @return array
     */
    protected function addColumns($data, $row)
    {
        foreach ($this->appendColumns as $key => $value) {
            $value['content'] = Helper::compileContent($value['content'], $data, $row, $this->shouldEscapeColumn($key));
            $data             = Helper::includeInArray($value, $data);
        }

        return $data;
    }

    /**
     * Process add index column.
     *
     * @param mixed $data
     * @param mixed $row
     * @return array
     */
    protected function addIndexColumn($data, $row)
    {
        if ($this->includeIndex) {
            $data[$indexColumn] = ++$this->start;
        }

        return $data;
    }

    /**
     * Process edit columns.
     *
     * @param mixed $data
     * @param mixed $row
     * @return array
     */
    protected function editColumns($data, $row)
    {
        foreach ($this->editColumns as $key => $value) {
            $value['content'] = Helper::compileContent($value['content'], $data, $row, $this->shouldEscapeColumn($key));
            Arr::set($data, $value['name'], $value['content']);
        }

        return $data;
    }

    /**
     * Setup additional DT row variables.
     *
     * @param mixed $data
     * @param mixed $row
     * @return array
     */
    protected function setupRowVariables($data, $row)
    {
        $processor = new RowProcessor($data, $row);

        return $processor
            ->rowValue('DT_RowId', $this->templates['DT_RowId'])
            ->rowValue('DT_RowClass', $this->templates['DT_RowClass'])
            ->rowData('DT_RowData', $this->templates['DT_RowData'])
            ->rowData('DT_RowAttr', $this->templates['DT_RowAttr'])
            ->getData();
    }

    /**
     * Remove declared hidden columns.
     *
     * @param array $data
     * @return array
     */
    protected function removeExcessColumns(array $data)
    {
        foreach ($this->excessColumns as $value) {
            unset($data[$value]);
        }

        return $data;
    }

    /**
     * Flatten array with exceptions.
     *
     * @param array $array
     * @return array
     */
    public function flatten(array $array)
    {
        $return     = [];
        $exceptions = ['DT_RowId', 'DT_RowClass', 'DT_RowData', 'DT_RowAttr'];

        foreach ($array as $key => $value) {
            if (in_array($key, $exceptions)) {
                $return[$key] = $value;
            } else {
                $return[] = $value;
            }
        }

        return $return;
    }

    /**
     * Escape column values as declared.
     *
     * @param array $output
     * @return array
     */
    protected function escapeColumns(array $output)
    {
        return array_map(function ($row) {
            if ($this->escapeColumns == '*') {
                $row = $this->escapeRow($row);
            } elseif (is_array($this->escapeColumns)) {
                $columns = array_diff($this->escapeColumns, $this->rawColumns);
                foreach ($columns as $key) {
                    array_set($row, $key, e(array_get($row, $key)));
                }
            }

            return $row;
        }, $output);
    }

    /**
     * Escape all values of row.
     *
     * @param array $row
     * @return array
     */
    protected function escapeRow(array $row)
    {
        $arrayDot = array_filter(array_dot($row));

        foreach ($arrayDot as $key => $value) {
            if (! in_array($key, $this->rawColumns)) {
                $arrayDot[$key] = e($value);
            }
        }

        foreach ($arrayDot as $key => $value) {
            array_set($row, $key, $value);
        }

        return $row;
    }

    /**
     * Whether to escape column or no.
     * @param string $key
     * @return bool
     */
    protected function shouldEscapeColumn($key)
    {
        return ! in_array($key, $this->rawColumns);
    }
}
