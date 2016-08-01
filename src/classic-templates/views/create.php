<?php
/* @var $gen \Idb\Crud\Commands\Crud */
/* @var $fields [] */
?>

@extends('<?=config('crud.layout')?>')

@section('content')

    <h2>Create a New <?=$gen->titleSingular()?></h2>

    <form action="/<?=$gen->route()?>" method="post">

        {{ csrf_field() }}
<?php foreach ( $fields as $field )  { ?>
<?php if( $str = \Idb\Crud\Db::getFormInputMarkup($field) ) { ?>

        <?=$str?>

<?php } ?>
<?php } ?>

        <button type="submit" class="btn btn-default">Submit</button>

    </form>

@endsection