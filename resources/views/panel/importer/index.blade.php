<?php

session()->forget('datosNuevos');
session()->forget('datosRepetidos');
session()->forget('datosIncompletos');
session()->forget('datosUnificar');
session()->forget('datosDescartados');
session()->forget('datosActualizar');

?>

@extends('layouts.panel-import-master')

{!!Html::style('styles/import.min.css')!!}

@section('content')
{{ csrf_field() }}

<div ng-controller="panelImporterController">

	<div class="col s12 centrada">

		<div class="container centrada">

			<h4>Seleccione Opción: </h4>

		</div>

	</div>

	<div class="container centrada">

		<div class="row  col s6 offset-s3">

			<div class="row centrada">

				<a href="{{ url('panel/importer/export') }}" class="waves-effect waves-light btn">DESCARGAR DATASET</a>

			</div>


			<div class="row centrada">

				<a href="{{ url('panel/importer/picker') }}" class="waves-effect waves-light btn">IMPORTAR DATASET</a>

			</div>

			<div class="row centrada">

				<a id="openModalButton" ng-click="openCleardbModal()" class="waves-effect waves-light btn">LIMPIAR BASE DE DATOS</a>

			</div>

		</div>

	</div>

</div>

<!-- Modal Structure -->
<div id="cleardbModal" class="modal">

	<div class="modal-content">

		<h4>¿Estas seguro qué deseás Limpiar la base de datos?</h4>
		<hr/>
		<p>Una vez confirmada la acción, no podrás volver atrás</p>
		<hr/>

	</div>

	<div class="modal-footer">

		<a href="" class=" modal-action modal-close waves-effect waves-green btn-flat">No</a>
		<a ng-click="cleardb()" href="" class=" modal-action waves-effect waves-green btn-flat">Si</a>

	</div>

</div>

@endsection


@section('js')

{!!Html::script('bower_components/ngmap/build/scripts/ng-map.min.js')!!}
{!!Html::script('bower_components/angucomplete-alt/dist/angucomplete-alt.min.js')!!}

{!!Html::script('scripts/panel/app.js')!!}
{!!Html::script('scripts/panel/controllers/importer/controller.js')!!}

@stop
