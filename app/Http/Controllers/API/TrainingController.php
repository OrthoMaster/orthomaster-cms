<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\OldContent;
use App\Models\Package;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TrainingController extends Controller
{
    /**
     * Get trainings and under levels
     */
    public function getAll(Request $request)
    {
        try {
            $user = $request->user();

            // Packages
            $userPackages = $user->packages()->get()->keyBy('id');
            $userPackageIds = $userPackages->keys()->toArray();

            $trainings = Training::getAllByUser($request->user()->id);

            $trainings->each(function ($training) use ($user, $userPackages, $userPackageIds) {
                $training->packages->each(function ($package) use ($user, $userPackages) {
                    // Check available
                    $package->available_flg = $user->student_flg || $userPackages->has($package->id);

                    // Get progress
                    $package->progress = $userPackages->has($package->id)
                        ? $userPackages->get($package->id)->pivot->progress
                        : 0;

                    $package->modules->each(function ($module) {
                        $module->demo_flg = $module->hasDemo();
                    });

                    // Lessons
                    // $package->modules->each(function ($module) {
                    //     $module->lessons->each(function ($lesson) {
                    //         if ($lesson->users->isNotEmpty()) {
                    //             $lesson->completed_flg = $lesson->users->first()->pivot->completed_flg ?? false;
                    //             $lesson->metadata = $lesson->users->first()->pivot->metadata ?? null;
                    //         } else {
                    //             $lesson->completed_flg = false;
                    //             $lesson->metadata = null;
                    //         }

                    //         unset($lesson->users);
                    //     });
                    // });
                });

                // Sort
                $userOwnedPackages = $training->packages->filter(function ($package) use ($userPackageIds) {
                    return in_array($package->id, $userPackageIds);
                });

                $otherPackages = $training->packages->filter(function ($package) use ($userPackageIds) {
                    return !in_array($package->id, $userPackageIds);
                });

                // Merge
                $training->packages = $userOwnedPackages->merge($otherPackages);
                $training->setRelation('packages', $training->packages);
            });

            return response()->json([
                'success' => true,
                'data' => $trainings
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-trainings']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get package
     */
    public function getPackage(Request $request, $packageId)
    {
        try {
            $user = $request->user();

            // Get user packages
            $userPackages = $user->packages()->get()->keyBy('id');

            // Get package with relations
            $package = Package::getByUser($packageId, $user->id);

            // Check available
            $package->available_flg = $user->student_flg || $userPackages->has($package->id);

            // Get progress
            $package->progress = $userPackages->has($package->id)
                ? $userPackages->get($package->id)->pivot->progress
                : 0;

            // Lessons
            $package->modules->each(function ($module) {
                $module->demo_flg = $module->hasDemo();

                $module->lessons->each(function ($lesson) {
                    if ($lesson->users->isNotEmpty()) {
                        $lesson->completed_flg = $lesson->users->first()->pivot->completed_flg ?? false;
                        $lesson->metadata = $lesson->users->first()->pivot->metadata ?? null;
                    } else {
                        $lesson->completed_flg = false;
                        $lesson->metadata = null;
                    }

                    unset($lesson->users);
                });
            });

            return response()->json([
                'success' => true,
                'data' => $package
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-package']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get content of lesson
     */
    public function getLessonContent(Request $request)
    {
        if (!$request->has('lessonId')) {
            return response()->json([
                'success' => false,
            ]);
        };

        try {
            $lesson = Lesson::find($request->lessonId);
            if (!$lesson) {
                return response()->json([
                    'success' => false,
                ]);
            }
            $lesson->users()->syncWithoutDetaching($request->user()->id);

            $content = DB::table('content')
                ->where('id_content', $lesson->content_id)
                ->where('type', 'lesson')
                ->first();

            $direction = $content->s3_path . "/" . $content->title . "/content.xml";
            $xmlContent = Storage::disk('s3_root')->get($direction);

            $baseDirectory = $content->s3_path . "/" . $content->title;
            $parsedContent = $this->processXmlImages($xmlContent, $baseDirectory);

            $content->xml_content = $parsedContent;

            return response()->json([
                'success' => true,
                'data' => $content ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['lesson-content']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    /**
     * Complete lesson
     */
    public function completeLesson(Request $request)
    {
        if (!$request->has('lessonId')) {
            return response()->json([
                'success' => false,
            ]);
        };

        try {
            $lesson = Lesson::find($request->lessonId);
            $lesson->users()->syncWithoutDetaching([
                $request->user()->id => [
                    'completed_flg' => true,
                    'updated_at' => now()
                ]
            ]);

            $package = $lesson->module->package;
            $totalCompletedLesson = Lesson::whereHas('module', function ($query) use ($package) {
                $query->where('package_id', $package->id);
            })
                ->whereHas('users', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)
                        ->where('completed_flg', true);
                })
                ->count();
            // Progress
            $progress = ($package->lesson_total > 0)
                ? round(($totalCompletedLesson / $package->lesson_total) * 100, 0)
                : 0;

            // Update progress
            $package->users()->syncWithoutDetaching([
                $request->user()->id => [
                    'progress' => $progress,
                    'updated_at' => now()
                ]
            ]);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['lesson-content']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    /**
     * Add more attribute for XML
     */
    public function processXmlImages($xmlContent, $baseDirectory)
    {
        try {
            // Regex to find tag image
            $pattern = '/<image([^>]*?)(\/?)>/i';

            $processedXml = preg_replace_callback($pattern, function ($matches) use ($baseDirectory) {
                $attributes = $matches[1];
                $selfClosing = $matches[2]; // '/' if self-closing, '' if not

                // Find attribute src
                if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $attributes, $srcMatch)) {
                    $src = $srcMatch[1];

                    // Create path
                    $fullPath = $baseDirectory . '/' . $src;

                    // Create URL from S3
                    $url = Storage::disk('s3_root')->url($fullPath);

                    // Check exist s3 URL
                    if (!preg_match('/s3\s*=\s*["\']/', $attributes)) {
                        // Add attribute url
                        $attributes .= ' s3="' . $url . '"';
                    }
                }

                // Return
                return '<image' . $attributes . $selfClosing . '>';
            }, $xmlContent);

            // Add isLast to quizz
            $processedXml = $this->addIsLastToQuiz($processedXml);

            return $processedXml;
        } catch (\Exception $e) {
            Log::channel('api')->error('Error processing XML images: ' . $e->getMessage());
            return $xmlContent; // Return original if error
        }
    }

    /**
     * Add attribute isLast to the last tag quiz
     */
    private function addIsLastToQuiz($xmlContent)
    {
        try {
            $pattern = '/<quiz([^>]*?)(\/?)>/i';

            preg_match_all($pattern, $xmlContent, $allMatches, PREG_OFFSET_CAPTURE);

            if (empty($allMatches[0])) {
                return $xmlContent;
            }

            // Get last index
            $lastQuizIndex = count($allMatches[0]) - 1;

            $currentIndex = 0;
            $processedXml = preg_replace_callback($pattern, function ($matches) use (&$currentIndex, $lastQuizIndex) {
                $attributes = $matches[1];
                $selfClosing = $matches[2];

                if ($currentIndex === $lastQuizIndex) {
                    if (!preg_match('/isLast\s*=\s*["\']/', $attributes)) {
                        $attributes .= ' isLast="true"';
                    }
                }

                $currentIndex++;
                return '<quiz' . $attributes . $selfClosing . '>';
            }, $xmlContent);

            return $processedXml;
        } catch (\Exception $e) {
            Log::channel('api')->error('Error adding isLast to quiz: ' . $e->getMessage());
            return $xmlContent;
        }
    }
}
