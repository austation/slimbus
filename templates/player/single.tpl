{% extends "index.tpl"%}
{% block content %}
<h2>{{player.label|raw}} 
  <small class="text-muted"> | <a href="http://www.byond.com/members/{{player.ckey}}" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt"></i> Byond</a> | <a href="https://tgstation13.org/tgdb/playerdetails.php?ckey={{player.ckey}}" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt"></i> tgdb</a></small>
</h2>
<hr>
<div class="row">
  <div class="col">
    <ul class="list-group">
      <li class="list-group-item">
        <strong>First Seen</strong> {{player.firstseen|timestamp}}
      </li>
      <li class="list-group-item">
        <strong>Last Seen</strong> {{player.lastseen|timestamp}}
      </li>
      <li class="list-group-item">
        <strong>Last IP Address</strong> <span class="tlp tlp-red">{{player.ip_real}}</span>
      </li>
      <li class="list-group-item">
        <strong>Last ComputerID</strong> <span class="tlp tlp-red">{{player.computerid}}</span>
      </li>
      <li class="list-group-item">
        <strong>Rounds</strong> {{player.roundCount}} (<a href="{{path_for('player.rounds',{'ckey': player.ckey})}}">List</a>)
      </li>
    </ul>
  </div>
  <div class="col">
    <ul class="list-group">
      <a class="list-group-item" href="{{path_for('player.messages',{'ckey': player.ckey})}}">
        <strong>Messages</strong> {{player.messageCount}}
      </a>
      <li class="list-group-item list-group-item-{{player.standing.class}}">
        <strong>Account Standing</strong> {{player.standing.text}}<br>
        <ul class="list-unstyled">
        {% for b in player.standing.bans %}
          <li>{{b.role}} (<a href="#"><i class="fas fa-ban"></i> {{b.id}}</a>) ({% if b.expiration_time %}Temp{% else %}Perma{% endif %})</li>
        {% endfor %}
        </ul>
      </li>
      <!-- <li class="list-group-item d-flex justify-content-between align-items-center">
        <strong>IPs seen</strong> <span class='badge badge-pill badge-primary'>{{player.ips|length}}</span>
      </li>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <strong>ComputerIDs seen</strong> <span class='badge badge-pill badge-primary'>{{player.cids|length}}</span>
      </li> -->
    </ul>
  </div>
  <div class="col">
    <ul class="list-group">
      <li class="list-group-item" style="background-color: {{player.design.backColor}}; color: {{player.design.foreColor}};">
        <span data-toggle="tooltip"><strong>Rank</strong> {{player.rank}}</span>
      </li>
      <li class="list-group-item">
        <strong>Connection Count</strong> {{player.connections}}
      </li>
      <li class="list-group-item">
        <strong>Playtime</strong> ~{{player.hours}} hours (<a href="{{path_for('player.roletime',{'ckey': player.ckey})}}">View Roles</a>)<br>
        <small>Since role time tracking was enabled</small>
        {% set total = player.ghost + player.living %}
        <div class="progress">
          <div class="progress-bar bg-danger" role="progressbar" style="width: {{player.ghost/total * 100}}%" data-toggle="tooltip" title="Ghost: {{player.ghost}} minutes"></div>
          <div class="progress-bar bg-success" role="progressbar" style="width: {{player.living/total * 100}}%" data-toggle="tooltip" title="Living: {{player.living}} minutes"></div>
        </div>
      </li>
      <li class="list-group-item">
        <strong>Byond Account Join Date</strong> {{player.accountjoindate}}
      </li>
    </ul>
  </div>
</div>
<hr>
<div class="row">
  <div class="col">
    <div class="card">
      <div class="card-header">
        <a data-target="#iplist" data-toggle="collapse">IP Addresses ({{player.ips|length}})</a>
      </div>
      <ul class="list-group list-group-flush collapse" id="iplist">
      {% for ip in player.ips %}
        {% include 'player/html/ips.html' %}
      {% endfor %}
      </ul>
    </div>
  </div>
  <div class="col">
    <div class="card">
      <div class="card-header">
        <a data-target="#cidlist" data-toggle="collapse">Computer IDs ({{player.cids|length}})</a>
      </div>
      <ul class="list-group list-group-flush collapse" id="cidlist">
      {% for cid in player.cids %}
        {% include 'player/html/cids.html' %}
      {% endfor %}
      </ul>
    </div>
  </div>
  <div class="col">
    <div class="card">
      <div class="card-header">
        <a data-target="#namelist" data-toggle="collapse"><span class="badge badge-info">BETA</span> Character Names 
          ({{player.names.deaths|length + player.names.manifest|length}})</a>
      </div>
      <ul class="list-group list-group-flush collapse" id="namelist">
        {% if player.names.manifest %}
        <strong>Manifest</strong>
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Name</th>
              <th>Times Seen</th>
            </tr>
          </thead>
          <tbody>
            {% for name in player.names.manifest %}
            <tr>
              <td>{{name.name}}</td>
              <td>{{name.times}}</td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
        <p>
        <small class="text-muted">Times being the number of times this ckey was found attached to this character name in a parsed manifest.log. <strong>THIS IS PRELIMINARY</strong></small>
        </p>
        {% endif %}
        <strong>Deaths Table</strong>
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Name</th>
              <th>Times Seen</th>
            </tr>
          </thead>
          <tbody>
            {% for name in player.names.deaths %}
            <tr>
              <td>{{name.name}}</td>
              <td>{{name.times}}</td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
        <p>
        <small class="text-muted">Times being the number of times this ckey has died while playing as this character name. Only the top five results are shown. <strong>THIS IS PRELIMINARY</strong></small>
        </p>
      </ul>
    </div>
  </div>
</div>
<hr>
<div class="row">
  <div class="col">
    <div class="card">
      <div class="card-header">
        <a data-target="#alts_ip" data-toggle="collapse"><span class="badge badge-info">BETA</span> Ckeys with the player's current IP address ({{player.alts.ip_alts|length}})</a>
      </div>
      <ul class="list-group list-group-flush collapse" id="alts_ip">
        {% for p in player.alts.ip_alts %}
          <li class="list-group-item">
            <a href="{{path_for('player.single',{'ckey': p})}}">{{p}}</a>
          </li>
        {% endfor %}
      </ul>
    </div>
  </div>
  <div class="col">
    <div class="card">
      <div class="card-header">
        <a data-target="#alts_cid" data-toggle="collapse"><span class="badge badge-info">BETA</span> Ckeys with the player's current CID ({{player.alts.cid_alts|length}})</a>
      </div>
      <ul class="list-group list-group-flush collapse" id="alts_cid">
      {% for p in player.alts.cid_alts %}
          <li class="list-group-item">
            <a href="{{path_for('player.single',{'ckey': p})}}">{{p}}</a>
          </li>
        {% endfor %}
      </ul>
    </div>
  </div>
</div>
{% endblock %}