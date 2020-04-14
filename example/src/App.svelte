<script>
  import ace from 'brace'
  console.log('ace', ace)
  import { onMount } from 'svelte';

  function fetch2(url, obj){
  //    var body = JSON.stringify(obj);
    //console.log('fetch', this)
    // TODO: Zamijeni ovo sranje s necim boljim
    var body = new FormData()
    if (obj)
        Object.keys(obj).forEach(function(key) {
          var tmpObj = obj[key]===null?'':obj[key]
          if (typeof tmpObj == 'object') tmpObj=JSON.stringify(tmpObj)
          body.append(key, tmpObj)
        });
//    if (self) self.set('loading', true)
    
    return new Promise(function(resolve, reject) {
      var response = fetch('../../cms/'+'api.php', {
          method: 'post',
          credentials:'same-origin',
  //            headers: {
  //             'Accept': 'application/json',
            'Content-Type': 'application/json',
  //              "authorization":'Bearer '+ authorization
  //            },
          body: body
        }).then(function(response){ 
//              if (self) self.set('loading', false)
              return response.json()
          })
        .then(function(j){ 
            //console.log('j',j); 
            if (j && j._message_action == "reload")  ractive.set('is_logedin',false)//document.location = document.location; // missing session
            resolve([j,null]) 
          })
        .catch(function(err){
//            if (self) self.set('loading', false)
            console.log('nework error!', err);
            //izitoast.error({ message: 'Network error!!!'});
            resolve([null,err])
        })
      })
  }
/*
  async function testf(){
    var [resp,err] = await fetch2('twigs', {query:'Twigs', id: "2"})
    console.log('resp', resp)
  }

  console.log('!!!!!!')
  testf()
*/
  let grid_el
  let editor
  let site_preview_url = 'moon'
  let files = []
  let files_map = {}
  let selected_file_name = 'moon.twig'
  let editor_value_changed = false
	let m = { x: 400, y: 300, state:'' };
  $: {
    if (!m.state && document.getElementById('sitePreview'))
      document.getElementById('sitePreview').style.pointerEvents='auto'
    if (!m.state && document.getElementById('adminPreview'))
      document.getElementById('adminPreview').style.pointerEvents='auto'
      
    if (m.state && document.getElementById('sitePreview'))
      document.getElementById('sitePreview').style.pointerEvents='none'
    if (m.state && document.getElementById('adminPreview'))
      document.getElementById('adminPreview').style.pointerEvents='none'
  }

  $: if (!m.state){
    console.log('resize');
    if (editor) editor.resize(true);
  }


	function handleMousemove(event) {
    if (event.buttons === 0) return
		if (m.state == 'downH' || m.state == 'down') m.x = event.clientX-10;
		if (m.state == 'downV' || m.state == 'down') m.y = event.clientY-10;
  }

	function selectFile(file, ix) {
    selected_file_name = file.name
    editor.setValue(file.html);
    editor.scrollToLine(0, true, true, function () {});
    let name_arr = selected_file_name.split('.')
    name_arr.pop();
    let trimmed_name = name_arr.join('.')
    console.log(file, ix, trimmed_name)
    site_preview_url = trimmed_name
  }

  async function save_twig_template(){
    var [resp,err] = await fetch2('twigs', {
      query:'Twigs', 
      _action:'update', 
      ...files_map[selected_file_name], 
      html: editor.getValue()
    })
    document.getElementById('sitePreview').contentWindow.location.reload()
    if (!err) await fetch_file_list()
    
  }
  
	onMount( async() => {
    console.log(grid_el, grid_el.clientWidth, document.body.clientWidth, grid_el.clientWidth/2 && document.body.clientWidth/2)
    window.grid_el = grid_el

    m.x = grid_el.clientWidth/2 && document.body.clientWidth/2 ;
    m.y = grid_el.clientHeight/2 && document.body.clientHeight/2;

    editor = ace.edit("editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/html");
    //editor.session.setMode("ace/mode/javascript");
    //var [resp,err] = await fetch2('twigs', {query:'Twigs', id: "2"})
    //editor.setValue(resp[0].html);
    await fetch_file_list()

    editor.session.on('change', function(delta) {
      editor_value_changed = (editor.getValue() != files_map[selected_file_name].html)
    });    
	});  

  async function fetch_file_list(){
    var [resp,err] = await fetch2('twigs', {query:'Twigs'})
    files_map = resp.reduce(function(r, e) {
      r[e.name] = e;
      return r;
    }, {});
    files = resp
    editor.setValue(files_map[selected_file_name].html);    

    editor.resize(true);
    editor.scrollToLine(0, true, true, function () {});

  }
</script>
<style>
	grid {
		box-sizing: content-box;
		display: grid;
		width:100%;
		height:100vh;
		grid-template-columns: var(--mx, auto) 5px auto;
  	grid-template-rows: var(--my, auto) 5px auto;
  	grid-template-areas: 
    "d1  vd1 d2 "
    "hd1 hdc hd2"
    "d3  vd2 d4";
	}
	.d1 {
		grid-area: d1;
    overflow: auto;
	}
	.d2 {
		grid-area: d2;
    overflow: auto;
	}
	.d3 {
		grid-area: d3;
    overflow: auto;
	}
	.d4 {
		grid-area: d4;
    overflow: auto;
	}
	.vd1 {
		grid-area: vd1;
		background-color:black;
		cursor: col-resize;
	}	
	.vd2 {
		grid-area: vd2;
		background-color:black;
		cursor: col-resize;
	}	
	.hd1 {
		grid-area: hd1;
		background-color:black;
		cursor: row-resize;
	}	
	.hd2 {
		grid-area: hd2;
		background-color:black;
		cursor: row-resize;
	}		
	.hdc {
		grid-area: hdc;
		background-color:black;
		cursor: move;
	}	
</style>
<window on:mouseup|capture={()=> { if(m.state) m.state = '' } }/>
<grid bind:this={grid_el} on:mousemove={handleMousemove} on:mouseup={()=> { if(m.state) m.state = '' } }
			style="--mx:{m.x}px; --my:{m.y}px">
	<div class="vd1" on:mousedown={()=> m.state = 'downH'}></div>
	<div class="vd2" on:mousedown={()=> m.state = 'downH'}></div>
	<div class="hd1" on:mousedown={()=> m.state = 'downV'}></div>
	<div class="hd2" on:mousedown={()=> m.state = 'downV'}></div>
	<div class="hdc" on:mousedown={()=> { m.state = 'down'}}></div>
	<div class="d1">
    <h2>Kako napraviti Testimonials sekciju</h2>
    <p>Detaljno objasnjenje...<br>.....
    </p>
  </div>
	<div class="d2" style="overflow: auto;display: flex; flex-flow: column;">
    <div class="input-group">
      <input bind:value="{site_preview_url}" type="text" class="form-control" placeholder="url" aria-label="url" aria-describedby="basic-addon2">
      <div class="input-group-append">
        <button on:click={()=> document.getElementById('sitePreview').contentWindow.location.reload() } 
        class="btn btn-outline-secondary" type="button">Refresh</button>
      </div>
    </div>

    <iframe id="sitePreview" title="Site Preview" src="../../{site_preview_url}" frameBorder="0" width="100%" height="100%">
    </iframe>
  </div>
	<div class="d3" style="overflow: auto;display: flex; flex-flow: column;">

    <div style="margin:0; color:white; background-color:#2F3129">
      HTML/Twig Code Editor  
      
    <button on:click={save_twig_template} disabled={!editor_value_changed} style="float:right" class="btn btn-outline-secondary" type="button">Save
    </button>
      
    </div>

    <div style="overflow: auto;display: flex; flex-flow: row; flex:1">
      <div class="list-group" style="overflow:auto">
        {#each files as file, ix}
          <a href="javascript:void(0)" on:click={()=> selectFile(file, ix)} 
          class="list-group-item list-group-item-action list-group-item-light {file.name==selected_file_name?'active':''}">
            {file.name}
          </a>
        {/each}
      </div>  
      <div id="editor" style="width:100%; height:100%; flex:1;">
      </div>
    </div>  

  </div>
	<div class="d4">
    <iframe  id="adminPreview" title="Admin" src="../../cms/admin/index.html" frameBorder="0" width="100%" height="100%">
    </iframe>
  </div>
</grid>
<!--
m.x = {m.x} px; m.y = {m.y} px
m.state = {m.state}
-->
