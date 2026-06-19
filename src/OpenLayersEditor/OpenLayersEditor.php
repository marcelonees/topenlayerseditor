<?php

namespace MarceloNees\Plugins\OpenLayersEditor;

use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Base\TStyle;

/**
 * OpenLayersEditor - Componente para edição de geometrias
 * 
 * @author Marcelo Barreto Nees <marcelo.linux@gmail.com>
 * @version 1.0
 * @package MarceloNees\Plugins\OpenLayersEditor
 * 
 * @example
 * $editor = new OpenLayersEditor([
 *     'useOLE' => true,
 *     'showToolbar' => true,
 *     'assetsPath' => 'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/'
 * ]);
 * $editor->setSize('100%', '500px');
 * $editor->setGeometry($geomData);
 */
class OpenLayersEditor extends TElement
{
    private $id;
    private $width = '100%';
    private $height = '500px';
    private $center = [-49.0904928, -26.504104];
    private $zoom = 15;
    private $geom = null;
    private $useOLE = true;
    private $options = [];
    private $initialized = false;

    /**
     * Construtor
     * @param array $options Opções do editor
     */
    public function __construct($options = [])
    {
        parent::__construct('div');

        $this->id = 'ol_editor_' . uniqid();
        $this->options = array_merge([
            'useOLE' => true,
            'showToolbar' => true,
            'assetsPath' => 'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/',
            'center' => [-49.0904928, -26.504104],
            'zoom' => 15,
            'layers' => [
                'osm' => [
                    'type' => 'tile',
                    'source' => 'osm',
                    'opacity' => 0.3
                ],
                'ortomosaico' => [
                    'type' => 'xyz',
                    'url' => 'https://www.jaraguadosul.sc.gov.br/geo/ortomosaico2020/{z}/{x}/{y}.png',
                    'maxZoom' => 19,
                    'opacity' => 1.0
                ]
            ]
        ], $options);

        $this->useOLE = $this->options['useOLE'];
        $this->center = $this->options['center'];
        $this->zoom = $this->options['zoom'];
    }

    /**
     * Define o tamanho do mapa
     * @param string|int $width Largura
     * @param string|int $height Altura
     * @return $this
     */
    public function setSize($width, $height)
    {
        $this->width = is_numeric($width) ? "{$width}px" : $width;
        $this->height = is_numeric($height) ? "{$height}px" : $height;
        return $this;
    }

    /**
     * Define a geometria inicial (GeoJSON)
     * @param object|array $geom Geometria em formato GeoJSON
     * @return $this
     */
    public function setGeometry($geom)
    {
        $this->geom = $geom;
        return $this;
    }

    /**
     * Define o centro do mapa
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return $this
     */
    public function setCenter($lat, $lng)
    {
        $this->center = [$lng, $lat];
        return $this;
    }

    /**
     * Define o zoom inicial
     * @param int $zoom Nível de zoom (0-19)
     * @return $this
     */
    public function setZoom($zoom)
    {
        $this->zoom = $zoom;
        return $this;
    }

    /**
     * Adiciona uma camada personalizada
     * @param string $name Nome da camada
     * @param array $config Configuração da camada
     * @return $this
     */
    public function addLayer($name, $config)
    {
        $this->options['layers'][$name] = $config;
        return $this;
    }

    /**
     * Renderiza o componente
     */
    public function show()
    {
        /* Estilos do container */
        $style = new TStyle("#{$this->id}");
        $style->width = $this->width;
        $style->height = $this->height;
        $style->border = '1px solid #ccc';
        $style->background = '#f0f0f0';
        $style->position = 'relative';
        $style->show();

        /* Container principal */
        $content = new TElement('div');
        $content->id = $this->id;
        $content->class = 'openlayers-editor';
        $content->add('Carregando editor...');

        parent::add($content);

        /* Carrega assets e inicializa */
        $this->loadAssets();
        $this->initMap();

        parent::show();
    }

    /**
     * Carrega os assets (CSS)
     */
    private function loadAssets()
    {
        $basePath = $this->options['assetsPath'];

        /* OpenLayers CSS */
        TStyle::importFromFile($basePath . 'ol.css');

        /* OLE CSS (se habilitado) */
        if ($this->useOLE) {
            TStyle::importFromFile($basePath . 'openlayers-editor.css');
        }
    }

    /**
     * Inicializa o mapa e o editor
     */
    private function initMap()
    {
        $id = $this->id;
        $center = $this->center;
        $zoom = $this->zoom;
        $geom = $this->geom ? json_encode($this->geom) : 'null';
        $useOLE = $this->useOLE ? 'true' : 'false';
        $assetsPath = $this->options['assetsPath'];
        $layers = json_encode($this->options['layers']);

        TScript::create("
            (function() {
                console.log('=== OpenLayersEditor - INICIANDO ===');
                console.log('ID:', '{$id}');
                console.log('Assets path:', '{$assetsPath}');
                console.log('Use OLE:', {$useOLE});
                
                /* ======================================== */
                /* CARREGA OS SCRIPTS EM ORDEM             */
                /* ======================================== */
                function loadScripts() {
                    var scripts = [];
                    
                    /* 1. OpenLayers (obrigatório) */
                    if (typeof ol === 'undefined') {
                        scripts.push('{$assetsPath}ol.js');
                    }
                    
                    /* 2. OLE (opcional) */
                    if ({$useOLE} && typeof ole === 'undefined') {
                        scripts.push('{$assetsPath}openlayers-editor.js');
                    }
                    
                    if (scripts.length === 0) {
                        console.log('✅ Todos os scripts já carregados');
                        createEditor();
                        return;
                    }
                    
                    console.log('📥 Carregando ' + scripts.length + ' script(s)...');
                    loadScriptsSequentially(scripts, 0);
                }
                
                function loadScriptsSequentially(scripts, index) {
                    if (index >= scripts.length) {
                        console.log('✅ Todos os scripts carregados');
                        createEditor();
                        return;
                    }
                    
                    console.log('  Carregando:', scripts[index]);
                    var script = document.createElement('script');
                    script.src = scripts[index];
                    script.onload = function() {
                        console.log('  ✅ Carregado:', scripts[index]);
                        loadScriptsSequentially(scripts, index + 1);
                    };
                    script.onerror = function() {
                        console.error('  ❌ Falha ao carregar:', scripts[index]);
                        /* Continua mesmo com erro */
                        loadScriptsSequentially(scripts, index + 1);
                    };
                    script.onload();
                    document.head.appendChild(script);
                }
                
                /* ======================================== */
                /* CRIA O EDITOR                          */
                /* ======================================== */
                function createEditor() {
                    console.log('createEditor - Iniciando...');
                    
                    /* Verifica se o OpenLayers foi carregado */
                    if (typeof ol === 'undefined') {
                        console.error('❌ OpenLayers não disponível');
                        document.getElementById('{$id}').innerHTML = 
                            '<div style=\"padding:20px;text-align:center;color:red;\">' +
                            '❌ OpenLayers não carregado. Verifique os assets.' +
                            '</div>';
                        return;
                    }
                    
                    /* Verifica a versão do OpenLayers */
                    if (ol.VERSION) {
                        console.log('✅ OpenLayers versão:', ol.VERSION);
                    }
                    
                    var container = document.getElementById('{$id}');
                    if (!container) {
                        console.error('❌ Container não encontrado');
                        setTimeout(createEditor, 500);
                        return;
                    }
                    console.log('✅ Container encontrado');
                    
                    /* Cria o target do mapa */
                    container.innerHTML = '';
                    var mapId = 'ol_map_' + Date.now();
                    var mapDiv = document.createElement('div');
                    mapDiv.id = mapId;
                    mapDiv.style.cssText = 'width: 100%; height: 100%;';
                    container.appendChild(mapDiv);
                    console.log('✅ Map target criado:', mapId);
                    
                    /* ======================================== */
                    /* CONFIGURA O MAPA                       */
                    /* ======================================== */
                    var center = ol.proj.fromLonLat({$center});
                    var geomData = {$geom};
                    var features = [];
                    var zoom = {$zoom};
                    
                    /* Processa a geometria */
                    if (geomData) {
                        console.log('Processando geometria...');
                        try {
                            var format = new ol.format.GeoJSON();
                            features = format.readFeatures(geomData, {
                                featureProjection: 'EPSG:3857'
                            });
                            console.log('  Features lidas:', features.length);
                            
                            if (features.length > 0) {
                                var tempSource = new ol.source.Vector({ features: features });
                                var extent = tempSource.getExtent();
                                if (extent) {
                                    center = ol.extent.getCenter(extent);
                                    zoom = 19;
                                    console.log('  Centro ajustado para a geometria');
                                }
                            }
                        } catch(e) {
                            console.warn('Erro ao processar geometria:', e);
                        }
                    }
                    
                    /* ======================================== */
                    /* CRIA AS CAMADAS                        */
                    /* ======================================== */
                    var layers = [];
                    var layerConfigs = {$layers};
                    
                    for (var name in layerConfigs) {
                        var config = layerConfigs[name];
                        var layer = null;
                        
                        if (config.type === 'tile') {
                            if (config.source === 'osm') {
                                layer = new ol.layer.Tile({
                                    source: new ol.source.OSM(),
                                    opacity: config.opacity || 1.0
                                });
                            } else {
                                /* Fallback para OSM */
                                layer = new ol.layer.Tile({
                                    source: new ol.source.OSM(),
                                    opacity: 0.3
                                });
                            }
                        } else if (config.type === 'xyz') {
                            layer = new ol.layer.Tile({
                                source: new ol.source.XYZ({
                                    url: config.url,
                                    maxZoom: config.maxZoom || 19
                                }),
                                opacity: config.opacity || 1.0
                            });
                        }
                        
                        if (layer) {
                            layers.push(layer);
                            console.log('  Camada adicionada:', name);
                        }
                    }
                    
                    /* Garante que pelo menos OSM esteja disponível */
                    if (layers.length === 0) {
                        layers.push(new ol.layer.Tile({
                            source: new ol.source.OSM()
                        }));
                        console.log('  Camada OSM adicionada (fallback)');
                    }
                    
                    /* ======================================== */
                    /* CRIA O MAPA                            */
                    /* ======================================== */
                    var map = new ol.Map({
                        target: mapId,
                        layers: layers,
                        view: new ol.View({
                            center: center,
                            zoom: zoom
                        }),
                        controls: ol.control.defaults().extend([
                            new ol.control.ScaleLine(),
                            new ol.control.FullScreen()
                        ])
                    });
                    
                    console.log('✅ Mapa criado');
                    window._editorMap = map;
                    
                    /* ======================================== */
                    /* CARREGA A GEOMETRIA                     */
                    /* ======================================== */
                    if (features.length > 0) {
                        var source = new ol.source.Vector({ features: features });
                        var layer = new ol.layer.Vector({
                            source: source,
                            name: 'edit_layer',
                            style: new ol.style.Style({
                                stroke: new ol.style.Stroke({
                                    color: '#00ff00',
                                    width: 3
                                }),
                                fill: new ol.style.Fill({
                                    color: 'rgba(0, 255, 0, 0.1)'
                                })
                            })
                        });
                        
                        map.addLayer(layer);
                        console.log('✅ Geometria carregada');
                        window._editorSource = source;
                        window._editorLayer = layer;
                        
                        /* ======================================== */
                        /* INICIALIZA O EDITOR                    */
                        /* ======================================== */
                        if ({$useOLE} && typeof ole !== 'undefined') {
                            console.log('Inicializando OLE...');
                            initOLE(map, source, container);
                        } else {
                            console.log('Inicializando fallback nativo...');
                            initNative(map, source, container);
                        }
                        
                    } else {
                        container.innerHTML = '<div style=\"position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.3);z-index:1000;text-align:center;\"><b>ℹ️ Nenhuma geometria carregada</b><br>Este imóvel não possui geometria.</div>';
                    }
                }
                
                /* ======================================== */
                /* INICIALIZA OLE                          */
                /* ======================================== */
                function initOLE(map, source, container) {
                    console.log('  Inicializando OLE...');
                    try {
                        var editor = new ole.Editor(map);
                        console.log('  ✅ Editor OLE criado');
                        
                        /* Controles */
                        var controls = [];
                        
                        /* Draw */
                        var draw = new ole.control.Draw({
                            source: source,
                            type: 'Polygon'
                        });
                        controls.push(draw);
                        console.log('  ✅ Draw control criado');
                        
                        /* Modify */
                        var modify = new ole.control.Modify({
                            source: source
                        });
                        controls.push(modify);
                        console.log('  ✅ Modify control criado');
                        
                        /* CAD */
                        var cad = new ole.control.CAD({
                            source: source
                        });
                        controls.push(cad);
                        console.log('  ✅ CAD control criado');
                        
                        editor.addControls(controls);
                        console.log('  ✅ Controles adicionados ao editor');
                        
                        window._oleEditor = editor;
                        window._oleControls = controls;
                        
                        /* Evento de atualização */
                        source.on('change', function() {
                            updateGeometryField(source);
                        });
                        
                        /* Atualização inicial */
                        setTimeout(function() {
                            updateGeometryField(source);
                        }, 500);
                        
                        console.log('✅ OLE inicializado com sucesso!');
                        console.log('  📌 Controles: Draw, Modify, CAD');
                        
                    } catch(e) {
                        console.error('  ❌ Erro ao inicializar OLE:', e);
                        console.log('  Usando fallback nativo...');
                        initNative(map, source, container);
                    }
                }
                
                /* ======================================== */
                /* FALLBACK NATIVO                         */
                /* ======================================== */
                function initNative(map, source, container) {
                    console.log('  Inicializando fallback nativo...');
                    
                    /* Select */
                    var select = new ol.interaction.Select({
                        condition: ol.events.condition.click,
                        style: new ol.style.Style({
                            image: new ol.style.Circle({
                                radius: 10,
                                fill: new ol.style.Fill({ color: '#ff6600' }),
                                stroke: new ol.style.Stroke({ color: '#ffffff', width: 3 })
                            })
                        })
                    });
                    map.addInteraction(select);
                    console.log('  ✅ Select criado');
                    
                    /* Translate */
                    var translate = new ol.interaction.Translate({
                        features: select.getFeatures()
                    });
                    map.addInteraction(translate);
                    console.log('  ✅ Translate criado');
                    
                    /* Modify */
                    var modify = new ol.interaction.Modify({
                        source: source,
                        deleteCondition: function(e) {
                            return ol.events.condition.shiftKeyOnly(e) && ol.events.condition.click(e);
                        },
                        insertVertexCondition: function(e) {
                            return ol.events.condition.doubleClick(e);
                        }
                    });
                    map.addInteraction(modify);
                    console.log('  ✅ Modify criado');
                    
                    /* Draw */
                    var draw = new ol.interaction.Draw({
                        source: source,
                        type: 'Polygon',
                        condition: function(e) {
                            return ol.events.condition.altKeyOnly(e) && ol.events.condition.click(e);
                        }
                    });
                    map.addInteraction(draw);
                    console.log('  ✅ Draw criado');
                    
                    /* Snap */
                    var snap = new ol.interaction.Snap({
                        source: source,
                        pixelTolerance: 12
                    });
                    map.addInteraction(snap);
                    console.log('  ✅ Snap criado');
                    
                    /* Evento de atualização */
                    source.on('change', function() {
                        updateGeometryField(source);
                    });
                    
                    /* Atualização inicial */
                    setTimeout(function() {
                        updateGeometryField(source);
                    }, 500);
                    
                    console.log('✅ Fallback nativo ativado com sucesso!');
                    console.log('  📌 Comandos: Select, Translate, Modify, Draw, Snap');
                }
                
                /* ======================================== */
                /* ATUALIZA CAMPO GEOM                      */
                /* ======================================== */
                function updateGeometryField(source) {
                    var currentFeatures = source.getFeatures();
                    if (currentFeatures.length > 0) {
                        try {
                            var format = new ol.format.GeoJSON();
                            var geomJson = format.writeFeatures(currentFeatures, {
                                dataProjection: 'EPSG:4326',
                                featureProjection: 'EPSG:3857'
                            });
                            
                            /* Dispara evento personalizado */
                            var event = new CustomEvent('geometryChanged', {
                                detail: { geometry: geomJson }
                            });
                            document.dispatchEvent(event);
                            console.log('✅ Geometria atualizada');
                        } catch(e) {
                            console.error('❌ Erro ao atualizar geometria:', e);
                        }
                    }
                }
                
                /* ======================================== */
                /* INICIA O CARREGAMENTO                   */
                /* ======================================== */
                console.log('Iniciando carregamento...');
                
                if (document.readyState === 'complete' || document.readyState === 'interactive') {
                    loadScripts();
                } else {
                    document.addEventListener('DOMContentLoaded', function() {
                        console.log('DOMContentLoaded - iniciando carregamento');
                        loadScripts();
                    });
                }
                
            })();
        ");
    }
}
