dondev2App.config(function ($interpolateProvider, $locationProvider) {
  $interpolateProvider.startSymbol('[[');
  $interpolateProvider.endSymbol(']]');
})

.controller('cityListController', function ($scope, $rootScope, $http, $interpolate, $translate) {

  $scope.page = 1;
  $scope.pages = 1;
  $scope.per_page = 20;
  $scope.search = " ";

  $scope.bottomPaginateLimit = 10;

  $scope.clearCiudades = function () {
    $scope.loadingCiudades = true;
    $http.get('../api/v1/panel/clear/ciudad/clearAllEmtpy')
    .success(function (response) {
      $scope.loadingCiudades = false;
      if (parseInt(response) > 0) {
        if($rootScope.selectedLanguage == "en")
          text = response + " cities have been removed that don't have any centers.";
        else
          text = "Se han removido " + response + " ciudades que no tenian centros.";
      }
      else{
        if($rootScope.selectedLanguage == "en")
          text = "No cities have been found with no centers.";
        else
          text = "No se han encontrado ciudades habilitadas sin centros.";
      }
      Materialize.toast(text, 5000);
      $scope.loadPage();

    });
  }

  $scope.clearCheckbox = function (id) {
    $("input.group1").prop("disabled", true);
  }

  $scope.clearPartidos = function () {
    $scope.loadingPartido = true;
    $http.get('../api/v1/panel/clear/partido/clearAllEmtpy')
    .success(function (response) {
      $scope.loadingPartido = false;
      for (let index = 0; index < response[1].length; index++) {
        var cadena = "#filled-in-box-" + response[1][index];
        $(cadena).prop('checked', false);
      }
      if (parseInt(response) > 0) {
        if($rootScope.selectedLanguage == "en")
          text = response + " districts have been removed that don't have any centers.";
        else
          text = "Se han removido " + response[0] + " partidos que no tenian centros.";
      }
      else{
        if($rootScope.selectedLanguage == "en")
          text = "No districts have been found with no centers.";
        else
          text = "No se han encontrado partidos habilitadas sin centros.";
      }
      Materialize.toast(text, 5000);
      $scope.loadPage();

    });
  }

  $scope.clearPais = function () {
    $scope.loadingPaises = true;
    $http.get('../api/v1/panel/clear/pais/clearAllEmtpy')
    .success(function (response) {
      $scope.loadingPaises = false;
      if (parseInt(response) > 0) {
        if($rootScope.selectedLanguage == "en")
          text = response + " countries have been removed that don't have any centers.";
        else
          text = "Se han removido " + response + " paises que no tenian centros."
      }
      else{
        if($rootScope.selectedLanguage == "en")
          text = "No countries have been found with no centers.";
        else
          text = "No se han encontrado paises habilitados sin centros.";
      }
      Materialize.toast(text, 5000);
      $scope.loadPage();
    });
  }
  $scope.clearProvincias = function () {
    $scope.loadingProvincias = true;
    $http.get('../api/v1/panel/clear/provincia/clearAllEmtpy')
    .success(function (response) {
      $scope.loadingProvincias = false;
      if (parseInt(response) > 0) {
        if($rootScope.selectedLanguage == "en")
          text = response + " states have been removed that don't have any centers.";
        else
          text = "Se han removido " + response + " provincias que no tenian centros.";
      }
      else{
        if($rootScope.selectedLanguage == "en")
          text = "No states have been found with no centers.";
        else
          text = "No se han encontrado provincias habilitadas sin centros.";
      }
      Materialize.toast(text, 5000);
      $scope.loadPage();

    });
  }

  $scope.loadPage = function () {
    $scope.loadingPrev = true;
    $http.get('../api/v1/panel/ciudad/panel/' + $scope.per_page + '/' + $scope.search + '?page=' + $scope.page)
    .success(function (response) {
      $scope.cities = response.data;
      $scope.cities.total = response.total;
      $scope.pages = response.last_page;
      for (var i = 0; i < $scope.cities.length; i++) {
        if (!$scope.cities[i].habilitado || $scope.cities[i].habilitado == "0") {
          $scope.cities[i].habilitado = false;
        } else {
          $scope.cities[i].habilitado = true;
        }
      }
      $scope.loadingPrev = false;
    })
    .error(function (response){
      console.log(response);
    });
  }

  $scope.loadPage();

  $scope.clearResults = function(){
    $scope.search = "";
    $scope.loadPage();
  }

  $scope.nextPage = function () {
    if ($scope.page < $scope.pages) {
      $scope.loadingPrev = true;
      $scope.page++;
      $scope.loadPage();
    }
  };

  $scope.previousPage = function () {
    if ($scope.page > 1) {
      $scope.loadingPrev = true;
      $scope.page--;
      $scope.loadPage();
    }
  };

  $scope.updateHidden = function (id, value, name) {
    var httpdata = {
      habilitado: !value[0][0]
    };
    $scope.loadingPrev = true;
    $http.post('../api/v1/panel/ciudad/update/' + id, httpdata)
    .success(function (response) {
      var text;
      if(!httpdata.habilitado) {
        if($rootScope.selectedLanguage == "en")
          text = name + " have been disabled. From now it is not selectable in combos.";
        else
          text = "Se ha ocultado " + name + " correctamente. Desde ahora no es seleccionable en los combos.";
      }
      else{
        if($rootScope.selectedLanguage == "en")
          text = name + " have been enabled. From now it is selectable in combos.";
        else
          text = "Se ha habilitado " + name + " correctamente. Desde ahora es seleccionable en los combos.";
      }
      Materialize.toast(text, 5000);
      $scope.loadingPrev = false;
    });
    return;
  };

  $rootScope.dynamicOrderFunction = 'nombre_ciudad';
  $rootScope.orderWith = function(filter){
    if ($rootScope.dynamicOrderFunction.indexOf(filter) > -1){
      if ($rootScope.dynamicOrderFunction.indexOf('-') > -1){
        $rootScope.dynamicOrderFunction = filter;  
      }
      else {
        $rootScope.dynamicOrderFunction = '-' + filter;   
      }
    }
    else {
      $rootScope.dynamicOrderFunction = filter;
    }
  }

  $rootScope.changeLanguage = function() {
    localStorage.setItem("lang", $rootScope.selectedLanguage);
    localStorage.setItem("selectedByUser", true);
    $translate.use($rootScope.selectedLanguage);
    $http.get('../changelang/' + $rootScope.selectedLanguage)
    .then(
      function(response) {
        if (response.statusText == 'OK') {
        } else {
          Materialize.toast('Intenta nuevamente mas tarde.', 5000);
        }
      },
      function(response) {
        Materialize.toast('Intenta nuevamente mas tarde.', 5000);
      });
    return;
  }

});
