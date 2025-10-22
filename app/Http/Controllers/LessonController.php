<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Training;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(int $moduleId)
    {
        $module = Module::findOrFail($moduleId);
        $lessons = Lesson::getByModuleId($module->id);

        return view('vendor.voyager.lessons.browse-with-module', compact('module', 'lessons'));
    }

    public function orderView($moduleId)
    {
        $module = Module::findOrFail($moduleId);

        // Get dataType
        $dataType = Voyager::model('DataType')->where('slug', '=', 'lessons')->first();


        // Set order config
        $dataType->order_column = 'order_dsp';
        $dataType->order_display_column = 'title';
        $dataType->order_direction = 'ASC';

        if (empty($dataType->order_column) || empty($dataType->order_display_column)) {
            return redirect()
                ->route('voyager.modules.index')
                ->with([
                    'message'    => 'Order column not configured',
                    'alert-type' => 'error',
                ]);
        }

        // Get lesson with module
        $model = app($dataType->model_name);
        $query = $model->query()->where('module_id', $moduleId);

        // Check soft deletes
        if ($model && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model))) {
            $query = $query->withTrashed();
        }

        $results = $query->orderBy($dataType->order_column, $dataType->order_direction)->get();

        $display_column = $dataType->order_display_column;

        $dataRow = Voyager::model('DataRow')
            ->whereDataTypeId($dataType->id)
            ->whereField($display_column)
            ->first();


        $view = "voyager::lessons.order";

        return Voyager::view($view, compact(
            'dataType',
            'display_column',
            'dataRow',
            'results',
            'module',
            'moduleId'
        ));
    }

    // Update order khi kéo thả
    public function updateOrder(Request $request, $moduleId)
    {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'lessons')->first();

        $order = json_decode($request->input('order'));
        $column = $dataType->order_column ?? 'order_dsp';

        foreach ($order as $index => $item) {
            Lesson::where('id', $item->id)
                ->where('module_id', $moduleId)
                ->update([$column => $index + 1]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function create(Request $request, $moduleId)
    {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'lessons')->first();

        $dataTypeContent = (strlen($dataType->model_name) != 0)
            ? new $dataType->model_name()
            : false;

        foreach ($dataType->addRows as $key => $row) {
            $dataType->addRows[$key]['col_width'] = $row->details->width ?? 100;
        }

        $dataTypeContent->module_id = $moduleId;
        $preSelectedModule = Module::find($moduleId);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = "voyager::lessons.edit-add";

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
