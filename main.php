<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HW5</title>
  <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.8/angular.min.js"></script>
  <script type="text/javascript" src="speller.js"></script>
  <script>
  var map ;
  $( function() {
    $.get('mapNYTimesDataFile.csv', function(data){
      map = csvJSON(data);
    });

    $.get("big.txt", null, function (data, textStatus) {
      var t1 = new Date();
      var lines = data.split("\n");
      var count = lines.length;
      lines.forEach(function (line) {
        setTimeout(function () {
          speller.train(line);
          count--;
          if (count == 0) {
            var t2 = new Date();
          }
        }, 0);
      });
    }, "text");

  	var getData = function (request, response) {
        var text = $("#query").val().toLowerCase().trim().replace(/ +(?= )/g,'');
        text = text.split(' ');
        var query = text[text.length - 1];
        var suggestions = [];
  	    $.getJSON(
  	        "http://localhost:8983/solr/origin/suggest?indent=on&q=" + query + "&start=0&wt=json",
  	        function (data) {
                sug = data.suggest.suggest[query].suggestions;
                for(var i = 0; i < sug.length; i ++){
                  if(sug[i].term != query && !sug[i].term.includes(".html"))
                    suggestions.push(sug[i].term);
                }
  	            // console.log(data.suggest.suggest[query].suggestions);
                // console.log(suggestions);
                response(suggestions);
  	        });
  	};

    //var csv is the CSV file with headers
    function csvJSON(csv){
      var lines=csv.split("\n");
      var result = {};
      for(var i=0;i<lines.length;i++){
        var currentline=lines[i].split(",");
          result[currentline[0]] = currentline[1];
      }
      //return result; //JavaScript object
      return result; //JSON
    }

    $( "#query" ).autocomplete({
      source: getData,
    });


  } );


    var app = angular.module('myApp', []);
    app.controller('myCtrl', function($scope,$http) {
      $scope.newFunc = function(){
        $("#query").val($scope.newresult);
        $scope.myFunc();
      };

      $scope.myFunc = function() {
          var word = $("#query").val().toLowerCase().trim();
          var text = word.replace(/ +(?= )/g,'');
          text = text.split(' ');
          setTimeout(function () {
            var newresult = '';
            for(var i = 0; i < text.length; i++){
              newresult += speller.correct(text[i]) + ' ';
            }
            newresult = newresult.trim();
            if(newresult != word){
              $("#spellcheck").show();
                $("#newresult").html(newresult);
                $scope.newresult = newresult;
            }else{
              $("#spellcheck").hide();
            }
          }, 0);
          //get result from solr
        $http.get("http://localhost:8983/solr/origin/select?fl=id,title&indent=on&q="+ word +"&wt=json")
        .then(function(response) {
            $scope.docs = response.data.response.docs;
            if($scope.docs.length < 1){
              $scope.docs = {};
              return;
            }
            for(let i = 0; i < $scope.docs.length; i++){
              $scope.docs[i]["url"] = map[$scope.docs[i].id.substring(65)];
              $scope.docs[i]["snippet"] = "";
              //get snippet
              $http.get($scope.docs[i].id.substring(45))
              .then(function(response) {
                var html = $.parseHTML(response.data, document, false);
                var doc = $scope.docs[i];
                $.each( html, function( m, el ) {
                  if(!doc.snippet.length && el.nodeName.toLowerCase() != "script" && el.nodeName.toLowerCase() != "title" && el.textContent.toLowerCase().includes(word)){
                      var result = el.textContent.match( /[\n|\w].*\w+[\n|\w]/g );
                      for(var j = 0; j < result.length; j++){
                          var temp = result[j].trim().toLowerCase();
                          if(temp.includes(word) ){
                              if(temp.length > 160){
                                var index1 = temp.search(word);
                                var index2 = temp.substring(index1 + word.length).search(word);
                                index2_copy = index2 + word.length + index1;
                                if(index2 > -1)
                                  doc.snippet = temp.substring(index1, index2_copy);
                                else if(temp.length < index1 + 160)
                                  doc.snippet = temp.substring(index1);
                                else
                                  doc.snippet = temp.substring(index1, index1 + 160);
                              }else{
                                doc.snippet = temp;
                              }
                              break;
                          }
                      }
                  }
                });
                
              });

            }
            // console.log($scope.docs);
        });
        

      };
    });

  </script>
    <style>
    a:link {
        text-decoration: none;
    }

    a:visited {
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    a:active {
        text-decoration: none;
    }
    
    a.title {
      color: #1A0DAB;
      font-size: 18px;
    }

    a.url {
      color: #006621;
      font-size: 14px;
    }

    body {
      font-family: arial, Times, serif;
    }
    .id {
      font-size: 14px;
      color:gray;
    }

    #newresult {
      text-decoration: underline;
      color: #00008B;
    }
  </style>
</head>
<body ng-app="myApp" ng-controller="myCtrl">
<div class="ui-widget">
  <label for="query">Search: </label>
  <input id="query" type="text" name="query"/>
  <input id="submit" type="submit" name="submit" value="Submit" ng-click="myFunc()"/>
</div>

<p id="spellcheck" hidden>Showing results for <a id="newresult" ng-click="newFunc()" target="_blank">{{newresult}}</a></p>

<div> 
  <table ng-repeat="x in docs" style="border: 10px solid white; text-align: left">
    <tr><td class= "id">{{x.id}}</td></tr>
    <tr><td><a class="title" ng-href="{{x.url}}" target="_blank">{{x.title[0]}}</a></td></tr>
    <tr><td><a class="url" ng-href="{{x.url}}" target="_blank">{{x.url}}</a></td></tr>
    <tr><td >{{x.snippet}}</td></tr>
  </table>
</div>
 
</body>
</html>