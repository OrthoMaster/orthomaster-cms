<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Training;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(int $trainingId)
    {
        $training = Training::findOrFail($trainingId);
        $lessons = Lesson::getByTrainingId($training->id);

        return view('vendor.voyager.lessons.browse-with-training', compact('training', 'lessons'));
    }

    public function orderView($trainingId)
    {
        $training = Training::findOrFail($trainingId);

        // Get dataType
        $dataType = Voyager::model('DataType')->where('slug', '=', 'lessons')->first();


        // Set order config
        $dataType->order_column = 'order_dsp';
        $dataType->order_display_column = 'title';
        $dataType->order_direction = 'ASC';

        if (empty($dataType->order_column) || empty($dataType->order_display_column)) {
            return redirect()
                ->route('voyager.trainings.index')
                ->with([
                    'message'    => 'Order column not configured',
                    'alert-type' => 'error',
                ]);
        }

        // Get lesson with training
        $model = app($dataType->model_name);
        $query = $model->query()->where('training_id', $trainingId);

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
            'training',
            'trainingId'
        ));
    }

    // Update order khi kéo thả
    public function updateOrder(Request $request, $trainingId)
    {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'lessons')->first();

        $order = json_decode($request->input('order'));
        $column = $dataType->order_column ?? 'order_dsp';

        foreach ($order as $index => $item) {
            Lesson::where('id', $item->id)
                ->where('training_id', $trainingId)
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

    public function create(Request $request, $trainingId)
    {
        $dataType = Voyager::model('DataType')->where('slug', '=', 'lessons')->first();

        $dataTypeContent = (strlen($dataType->model_name) != 0)
            ? new $dataType->model_name()
            : false;

        foreach ($dataType->addRows as $key => $row) {
            $dataType->addRows[$key]['col_width'] = $row->details->width ?? 100;
        }

        $dataTypeContent->training_id = $trainingId;
        $preSelectedTraining = Training::find($trainingId);

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
