{% extends "index.tpl"%}
{% block content %}
<div class="row">
  <div class="col">
  {% set vars = {
    'nbPages': round.pages,
    'currentPage': round.page,
    'url': path_for('round.index')
    } 
  %}
  {% include 'components/pagination.html' with vars %}
  </div>
  <div class="col">
    <p class="text-muted text-right">Showing rounds between {{round.firstListing}} UTC and {{round.lastListing}} UTC<br>
      <a href="{{app.url}}round.php?names">Some famous Nanotrasen Space Stations</a>
    </p>
  </div>
</div>
  <table class="table table-sm table-bordered">
    <thead>
      <tr>    
        <th>ID</th>   
        <th>Mode</th>   
        <th>Result</th>   
        <th>Map</th>    
        <th>Duration</th>   
        <th>Start</th>    
        <th>End</th>    
        <th>Server</th>   
      </tr>   
    </thead>
    <tbody>
    {% for round in rounds %}
      {% include('rounds/html/listingrow.tpl') %}
    {% endfor %}
    </tbody>
  </table>
  {% include 'components/pagination.html' with vars %}
{% endblock %}