<script>
//  import ace from 'brace'
//  console.log('ace', ace)

  // ace NOV
//
  window.document.domain = "evolvitcms.com";
  import ace from '../ace-builds-master/src-noconflict/ace.js'

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
      var response = fetch('../cms/'+'api.php', {
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
  let site_preview_url = window.location.hash.slice(1) 
  $: if (site_preview_url) window.location.hash = site_preview_url 
  let view_generated_source_promise
  $: if (site_preview_url && view_generated_source) 
    view_generated_source_promise = fetch('../'+site_preview_url).then((r)=>r.text())

  let files = []
  let files_map = {}
  let selected_file_name = site_preview_url + '.twig'
  let editor_value_changed = false
  let m = { x: 400, y: 300, state:'' };
  let view_generated_source=false
  $: userSelect = m.state?'none':'auto' //user-select: none
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

//document.body.clientWidth/2 
//document.body.clientHeight/2
		if (m.state == 'downH' || m.state == 'down') m.x = event.clientX / document.body.clientWidth ;
		if (m.state == 'downV' || m.state == 'down') m.y = event.clientY / document.body.clientHeight ;
  }

	function selectFile(file, ix) {
    selected_file_name = file.name
    editor.setValue(file.html);
    editor.scrollToLine(0, true, true, function () {});
    let name_arr = selected_file_name.split('.')
    name_arr.pop();
    let trimmed_name = name_arr.join('.')
    //console.log(file, ix, trimmed_name)
    site_preview_url = trimmed_name
  }

  async function save_twig_template(){
    var [resp,err] = await fetch2('twigs', {
      query:'Twigs', 
      _action:'update', 
      ...files_map[selected_file_name], 
      html: editor.getValue()
    })
    if (document.getElementById('sitePreview'))
      document.getElementById('sitePreview').contentWindow.location.reload()
    else 
      view_generated_source_promise = fetch('../'+site_preview_url).then((r)=>r.text())

    if (!err) await fetch_file_list()
    editor.focus();
    
  }
  
  let postMsg
	onMount( async() => {
    postMsg = window.document.getElementById('headerFrameEl').contentWindow.postMessage
    let __seq = 0
    function postMessagePromise(msg){
      return new Promise(function(resolve, reject) {
        let seq = ++__seq;

        let event_ref = window.addEventListener("message", function(resp){
          if (resp && resp.data && resp.data.__seq && resp.data.__seq == seq){
            window.removeEventListener("message", event_ref)
            resolve(resp.data)
          }
        }, false);

        postMsg({...msg, __seq:seq}, 'http://evolvitcms.com/googleauthframe')
      });
    }
    window.postMessagePromise = postMessagePromise
    window.postMsg = postMsg

    // test
    setTimeout(function(){
      postMessagePromise({namespace:'PlaygroundProjectsAdd'})
      .then((r)=>{ console.log('JEEE', r) })
    }, 6000)


    //console.log(grid_el, grid_el.clientWidth, document.body.clientWidth, grid_el.clientWidth/2 && document.body.clientWidth/2)
    window.grid_el = grid_el

    m.x = 0.5 ;
    m.y = 0.5 ;

//    m.x = grid_el.clientWidth/2 && document.body.clientWidth/2 ;
//    m.y = grid_el.clientHeight/2 && document.body.clientHeight/2;

    ace.config.set('basePath', 'ace-builds-master/src-noconflict/')
    editor = ace.edit("editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/twig");
    editor.commands.addCommand({
        name: 'Save',
        bindKey: {win: 'Ctrl-S',  mac: 'Command-S'},
        exec: function(editor) {
            save_twig_template()
        },
        readOnly: false
    });    
    editor.session.setOptions({
      //mode: "ace/mode/javascript",
      tabSize: 2,
      useSoftTabs: true
    });
    //editor.session.setMode("ace/mode/javascript");
    //var [resp,err] = await fetch2('twigs', {query:'Twigs', id: "2"})
    //editor.setValue(resp[0].html);
    await fetch_file_list()
    editor.scrollToLine(0, true, true, function () {});

    editor.session.on('change', function(delta) {
      if (!selected_file_name || !files_map || !files_map[selected_file_name]) return
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
    if (!selected_file_name || !files_map || !files_map[selected_file_name]) return
    
    if (editor.getValue() != files_map[selected_file_name].html){
      editor.setValue(files_map[selected_file_name].html);    
      editor.resize(true);
    }

  }

  async function addNewFile(){
    let newFileName = prompt('Enter file name:')
    if (!newFileName) return
    if (!newFileName.endsWith('.twig'))
      newFileName = newFileName + '.twig';
    
    var [resp,err] = await fetch2('twigs', {
      query:'Twigs', 
      _action:'insert', 
      name: newFileName, 
      html: ''
    })
    if (!err) await fetch_file_list()
    selected_file_name = newFileName
    selectFile(files_map[selected_file_name])
//    document.getElementById('sitePreview').contentWindow.location.reload()
    
    //files = [{id:'', name:'novi.twig', html:'prazno'}, ...files]
  }
  async function removeFile(file){
    let conf = confirm(`Remove file '${file.name}'?`)
    if (!conf) return

    var [resp,err] = await fetch2('twigs', {
      query:'Twigs', 
      _action:'delete', 
      ...file
    })
    if (!err) await fetch_file_list()
    selected_file_name = null
    //selectFile(files_map[selected_file_name])
  }





  let headerFrameEl

  window.onmessage = function(e){
    console.log('onmessage u plejgr', e)

  };
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
    "hd1 hdc d2"
    "d3  vd2 d2";
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
  /*
	.hd2 {
		grid-area: hd2;
		background-color:black;
		cursor: row-resize;
	}	
  */	
	.hdc {
		grid-area: hdc;
		background-color:black;
		cursor: move;
	}	
  .remove-btn{
    visibility: hidden;
  }
  .list-group-item:hover .remove-btn{
    visibility: visible;
  }
</style>
<window on:mouseup|capture={()=> { if(m.state) m.state = '' } }/>
<div>
  <iframe  bind:this={headerFrameEl} id="headerFrameEl" title="Header" src="http://evolvitcms.com/googleauthframe" frameBorder="0" width="100%" height="60px">
  </iframe>
</div>
<grid bind:this={grid_el} on:mousemove={handleMousemove} on:mouseup={()=> { if(m.state) m.state = '' } }
			style="--mx:{100*m.x}%; --my:{100*m.y}%; user-select:{userSelect}">
	<div class="vd1" on:mousedown={()=> m.state = 'downH'}></div>
	<div class="vd2" on:mousedown={()=> m.state = 'downH'}></div>
	<div class="hd1" on:mousedown={()=> m.state = 'downV'}></div>
  <!--
	<div class="hd2" on:mousedown={()=> m.state = 'downV'}></div>
  -->
	<div class="hdc" on:mousedown={()=> { m.state = 'down'}}></div>
	<div class="d1">
    <iframe  id="adminPreview" title="Admin" src="../cms/admin/index.html" frameBorder="0" width="100%" height="100%">
    </iframe>
  </div>

	<div class="d2" style="overflow: auto;display: flex; flex-flow: column;">
    <div style="height: 3.75rem;; margin:0; color:white; background-color:#203554; padding-left:1rem; display: flex;align-items: center;justify-content: space-between;">
      <div>
        <img src="bulb.png" alt="buld" style="margin-right: 0.5rem;">
        <span>Preview</span>
      </div>
      <div style="display:flex">

        <div on:click={()=> view_generated_source=false}
        style="border-bottom-width: 4px; cursor: pointer;
          border-bottom-style: {!view_generated_source?'solid':'hidden'};
          border-bottom-color: white;
          padding: 0.5rem;
          margin: 0.5rem;">
          <img src="prev_browser.png" alt="browser" style="margin-right:0.5rem">
          Browser
        </div>

        <div on:click={()=> view_generated_source=true}
        style="border-bottom-width: 4px; cursor: pointer;
          border-bottom-style: {view_generated_source?'solid':'hidden'};
          border-bottom-color: white;
          padding: 0.5rem;
          margin: 0.5rem;">
          <img src="prev_source.png" alt="browser" style="margin-right:0.5rem">
          Generated source
        </div>

      </div>
    </div>
    <div class="input-group">
      <input bind:value="{site_preview_url}" type="text" class="form-control" placeholder="url" aria-label="url" aria-describedby="basic-addon2">
      <div class="input-group-append">
        <button on:click={()=> document.getElementById('sitePreview').contentWindow.location.reload() } 
        class="btn btn-outline-secondary" type="button">
        <span class="oi oi-loop-circular" title="Refresh" aria-hidden="true"></span>
        </button>
        <a class="btn btn-outline-secondary" href="../{site_preview_url}" target="_blank" role="button">
        <span class="oi oi-external-link" title="Open in new tab" aria-hidden="true"></span>
        </a>
      </div>
    </div>

    {#if view_generated_source }
    <pre style="flex:1; tab-size: 2;">
    <code>
      {#await view_generated_source_promise}
        <!-- promise is pending -->
        <p>loading...</p>
      {:then value}
        <!-- promise was fulfilled -->
        {value}
      {:catch error}
        <!-- promise was rejected -->
        <p>Something went wrong: {error.message}</p>
      {/await}
    </code>
    </pre>
    {:else}
    <iframe style="flex:1" id="sitePreview" title="Site Preview" src="../{site_preview_url}" frameBorder="0" width="100%" height="100%">
    </iframe>
    {/if }
  </div>


	<div class="d3" style="overflow: auto;display: flex; flex-flow: column;">

    <div style="margin:0; color:white; background-color:#203554; padding-left:1rem; display: flex;align-items: center;justify-content: space-between;">
      <span>
        <img src="bulb.png" style="margin-right: 0.5rem;" alt="buld">
        HTML/Twig Code Editor 
      </span> 
      
    <button on:click={save_twig_template} disabled={!editor_value_changed} style="float:right" class="btn btn-outline-secondary" type="button">
      <span class="oi oi-check"></span> Save
    </button>
      
    </div>

    <div style="overflow: auto;display: flex; flex-flow: row; flex:1">
      <div class="list-group" style="overflow:auto">
          <a href="javascript:void(0)" on:click={addNewFile} 
          class="list-group-item list-group-item-action list-group-item-light">
            
            <strong><span class="oi oi-plus"></span> Add new file</strong>
          </a>

        {#each files as file, ix}
          <a href="javascript:void(0)" on:click={()=> selectFile(file, ix)}
          style="padding-right: 0.5rem;" 
          class="list-group-item list-group-item-action list-group-item-light {file.name==selected_file_name?'active':''}">
            {file.name}
              <span on:click|stopPropagation={ ()=> removeFile(file) }
                style="float: right; padding: 0.1rem;" 
                class="oi oi-x remove-btn" 
                title="Delete file" 
                aria-hidden="true">
              </span> 
          </a>
        {/each}
      </div>  
      <div id="editor" style="width:100%; height:100%; flex:1;">
      </div>
    </div>  

  </div>
  <!--
	<div class="d4">
    <h2>Kako napraviti Testimonials sekciju</h2>
    <p>Detaljno objasnjenje...<br>.....
    </p>
  </div>
  -->
</grid>
<!--
m.x = {m.x} px; m.y = {m.y} px
m.state = {m.state}
-->
