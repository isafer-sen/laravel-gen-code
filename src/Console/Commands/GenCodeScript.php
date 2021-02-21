<?php

namespace Gen\Code\Console\Commands;

use Illuminate\Console\Command;

class GenCodeScript extends Command
{
    private static $modelNamespace = "App\\Models\\";
    private static $postNamespace = "App\\Http\\Requests\\";
    private static $ctrlNamespace = "App\\Http\\Controllers\\";
    /**
     * The name and signature of the console command.
     * rt   路由标识
     * ctrl 控制器标识
     * re   备注标识
     * @var string
     */
    protected $signature = 'make:newctrl {rt}{ctrl}{re}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create my menu';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $rt = $this->argument("rt"); # 路由标识
        $ctrl = $this->argument("ctrl"); # 控制器标识
        $re = $this->argument("re"); # 备注
        $boolInfile = $this->inspectionFile($ctrl);
        $boolIsCtrl = $this->isCtrl($ctrl);
        if (!$boolIsCtrl) die("\nThe ctrl does not meet the rules\n");
        if ($boolInfile) die("\nThe folder already exists and the program stops running\n");
        # 创建路由
        $this->actionRoute($rt, $ctrl, $re);
        # 创建模型
        $this->actionModel($ctrl);
        # 创建表单
        $this->actionPost($ctrl);
        # 创建控制器
        $this->actionController($ctrl);
        $this->info("\nAdd route successfully!\n");
        exit(0);
    }

    // 控制器必须带Controller
    protected function isCtrl($ctrl)
    {
        if (strpos($ctrl, "Controller") !== false) {
            return true;
        } else {
            return false;
        }
    }

    // 检验文件是存在 防止文件被覆盖
    protected function inspectionFile($ctrl)
    {
        $dir = base_path(self::$ctrlNamespace);
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file == $ctrl . ".php") return true;
                }
                closedir($dh);
                return false;
            }
        }
        return true;
    }


    // 操作路由
    protected function actionRoute($rt, $ctrl, $re)
    {
        $filePath = base_path("routes/api.php");
        $search = "#END";
        $fileContent = $this->fileContent($filePath);
        if (strpos($fileContent, "#END")) { // 包含
            $replace = $this->writeRouteContent($rt, $ctrl, $re);
            $content = $this->strReplace($search, $replace, $fileContent);
            $this->writeFile($filePath, $content);
        } else { // 不包含
            $content = $this->writeBaseRouteContent($rt, $ctrl, $re);
            $this->writeFile($filePath, $content);
        }


    }

    // 操作模型
    protected function actionModel($ctrl)
    {
        $search = "#TABLE";
        $classSearch = "DummyClass";
        $model = $this->strReplace("Controller", "", $ctrl);
        $execModel = self::$modelNamespace . $model;
        \Artisan::call("make:model", ["name" => $execModel]);
        $filePath = base_path($execModel . ".php");
        $fcontent = $this->fileContent(__DIR__ . '/stubs/model.stub');
        $content = $this->strReplace($classSearch, $model, $fcontent, "Y");
        $this->writeFile($filePath, $content);
        $table = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $model));
        $filePath = base_path(self::$modelNamespace . $model . '.php');
        $replace = $this->writeTableContent($table);
        $fileContent = $this->fileContent($filePath);
        $content = $this->strReplace($search, $replace, $fileContent);
        $this->writeFile($filePath, $content);
    }

    // 操作表单
    protected function actionPost($ctrl)
    {
        $classSearch = "DummyClass";
        $vaPost = $this->strReplace("Controller", "Post", $ctrl);
        \Artisan::call("make:request", ["name" => $vaPost]); # 创建表单验证
        $filePath = base_path(self::$postNamespace . $vaPost . ".php");
        $fcontent = $this->fileContent(__DIR__ . '/stubs/request.stub');
        $content = $this->strReplace($classSearch, $vaPost, $fcontent, "Y");
        $this->writeFile($filePath, $content);
    }

    // 操作控制器
    protected function actionController($ctrl)
    {
        $modelSearch = "#MODEL";
        $postSearch = "#POST";
        $classSearch = "DummyClass";
        $vaPost = $this->strReplace("Controller", "Post", $ctrl);
        $model = $this->strReplace("Controller", "", $ctrl);
        \Artisan::call("make:controller", ["name" => $ctrl]); # 创建控制器
        $filePath = base_path(self::$ctrlNamespace . $ctrl . ".php");
        $fcontent = $this->fileContent(__DIR__ . '/stubs/controller.plain.stub');
        $content = $this->strReplace($classSearch, $ctrl, $fcontent, "Y");
        $this->writeFile($filePath, $content);
        $ctrlFilePath = base_path(self::$ctrlNamespace . $ctrl . ".php");
        $modelReplace = $this->writeModelNamespaceContent($model);
        $modelFileContent = $this->fileContent($ctrlFilePath);
        $firstExecContent = $this->strReplace($modelSearch, $modelReplace, $modelFileContent, "Y");
        $postReplace = $this->writePostNamespaceContent($vaPost);
        $content = $this->strReplace($postSearch, $postReplace, $firstExecContent, "Y");
        $this->writeFile($ctrlFilePath, $content);
    }

    protected function writeFile($filePath, $content)
    {
        file_put_contents($filePath, $content);
    }

    protected function fileContent($filePath)
    {
        $handle = fopen($filePath, "r");
        $contents = fread($handle, filesize($filePath));
        fclose($handle);
        return $contents;
    }

    /**
     * @param $search | 寻找的内容
     * @param $replace | 要替换的内容
     * @param $subject | 原字符串
     * @param string $flag
     * @return mixed
     */
    protected function strReplace($search, $replace, $subject, $flag = "Y")
    {
        if ($flag == "Y") { # 替换全部相同内容
            $subject = str_replace($search, $replace, $subject);
            return $subject;
        } else { # 替换最后一次出现的位置
            $pos = strrpos($subject, $search);
            if ($pos !== false) {
                $subject = substr_replace($subject, $replace, $pos, strlen($search));
            }
            return $subject;
        }
    }

    protected function writePostNamespaceContent($vaPost)
    {
        return <<< STR
{$vaPost}
STR;
    }

    protected function writeModelNamespaceContent($model)
    {
        return <<< STR
{$model}
STR;
    }

    protected function writeTableContent($table)
    {
        return <<< STR
{$table}
STR;
    }

    protected function writeRouteContent($rt, $ctrl, $re)
    {
        return <<< HTML
        
    # {$re}       
    Route::get("{$rt}-index", '{$ctrl}@index');
    Route::post("{$rt}-create", "{$ctrl}@create");
    Route::post("{$rt}-delete", "{$ctrl}@delete");
    Route::get("{$rt}-show", "{$ctrl}@show");
    Route::post("{$rt}-edit/{id}", "{$ctrl}@edit")
        ->where(["id" => "[0-9]+"]);
    #END

HTML;
    }


    protected function writeBaseRouteContent($rt, $ctrl, $re)
    {
        return <<<HTML
<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request \$request) {
    return \$request->user();
});


Route::group(['prefix' => '/v1'], function () {
    # {$re}       
    Route::get("{$rt}-index", '{$ctrl}@index');
    Route::post("{$rt}-create", "{$ctrl}@create");
    Route::post("{$rt}-delete", "{$ctrl}@delete");
    Route::get("{$rt}-show", "{$ctrl}@show");
    Route::post("{$rt}-edit/{id}", "{$ctrl}@edit")
        ->where(["id" => "[0-9]+"]);
    #END
});
HTML;

    }
}
