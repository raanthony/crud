<?php
/* @var $gen \Idb\Crud\Commands\Crud */
?>
<?='<?php'?>

namespace App\Http\Controllers{!! $gen->getBaseRoute() !!};

use App{!! $gen->getModelDir() !!}\{{$gen->modelClassName()}};
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class {{$gen->controllerClassName()}} extends Controller
{
    public $viewDir = "{{strtolower($gen->baseRoute) ? $gen->baseRoute . ".":""}}{{$gen->viewsDirName()}}";

    public function index()
    {
        $records = {{$gen->modelClassName()}}::findRequested();
        return $this->view( "index", ['records' => $records] );
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return $this->view("create");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store( Request $request )
    {
        $this->validate($request, {{$gen->modelClassName()}}::validationRules());

        {{$gen->modelClassName()}}::create($request->all());

        //return redirect('/{{$gen->route()}}');
        return redirect(route('{{$gen->generateRouteAction('index')}}'));
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, {{$gen->modelClassName()}} ${{$gen->modelVariableName()}})
    {
        return $this->view("show",['{{$gen->modelVariableName()}}' => ${{$gen->modelVariableName()}}]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, {{$gen->modelClassName()}} ${{$gen->modelVariableName()}})
    {
        return $this->view( "edit", ['{{$gen->modelVariableName()}}' => ${{$gen->modelVariableName()}}] );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, {{$gen->modelClassName()}} ${{$gen->modelVariableName()}})
    {
        if( $request->isXmlHttpRequest() )
        {
            $data = [$request->name  => $request->value];
            $validator = \Validator::make( $data, {{$gen->modelClassName()}}::validationRules( $request->name ) );
            if($validator->fails())
                return response($validator->errors()->first( $request->name),403);
            ${{$gen->modelVariableName()}}->update($data);
            return "Record updated";
        }

        $this->validate($request, {{$gen->modelClassName()}}::validationRules());

        ${{$gen->modelVariableName()}}->update($request->all());

        return redirect(route('{{$gen->generateRouteAction('index')}}'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, {{$gen->modelClassName()}} ${{$gen->modelVariableName()}})
    {
        ${{$gen->modelVariableName()}}->delete();
        return redirect(route('{{$gen->generateRouteAction('index')}}'));
    }

    protected function view($view, $data = [])
    {
        return view($this->viewDir.".".$view, $data);
    }

}
