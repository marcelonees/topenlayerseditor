# OpenLayersEditor

Componente para edição de geometrias usando OpenLayers Editor (OLE), desenvolvido para o Framework Adianti.

## Instalação

```bash
composer config repositories.topenlayerseditor vcs https://github.com/marcelonees/topenlayerseditor
composer require marcelonees/topenlayerseditor @dev
composer require marcelonees/topenlayerseditor dev-main
```

## Uso

```php
use MarceloNees\Plugins\OpenLayersEditor\OpenLayersEditor;

$this->editor = new OpenLayersEditor([
    'center' => [-49.0904928, -26.504104],
    'showToolbar' => true,
    'toolbarButtons' => [
        'select'    => '🔍 Selecionar',
        'draw'      => '✏️ Desenhar',
        'modify'    => '🔧 Modificar',
        'undo'      => '↩️ Voltar',
        'redo'      => '↪️ Refazer',
    ],
    'showToolbarLabels' => true,  // Mostra labels na toolbar
    'toolbarPosition' => 'top-right',  // Posição da toolbar
    'showLayerControl' => true,
    'layers' => [
        'osm' => [
            'type' => 'tile',
            'source' => 'osm',
            'opacity' => 0.5,
            'visible' => false,
            'title' => 'OpenStreetMap'
        ],
        'satelite' => [
            'type' => 'xyz',
            'url' => 'https://{a-d}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png',
            'maxZoom' => 19,
            'opacity' => 1.0,
            'visible' => true,
            'title' => 'Carto Voyager'
        ],
    ]
]);
$this->editor->setSize('100%', '100%');
$this->geom_array = json_decode($geojson);
$this->editor->setGeometry($this->geom_array);        

$this->editor->addLayer('ortomosaico',          ['type' => 'xyz', 'title' => 'Ortomosaico 2020', 'url' => 'https://www.meuservidor.com.br/geo/ortomosaico/{z}/{x}/{y}.png', 'maxZoom'   => 19]);
$this->editor->addLayer('lim_municipal',        ['type' => 'wms', 'title' => 'Limite Municipal', 'url' => 'https://geoserver.meuservidor.com.br/gs/geoserver-main/PMJS/wms', 'params'   => ['LAYERS' => 'lim_municipal']]);
$this->editor->addLayer('lim_urbano',           ['type' => 'wms', 'title' => 'Limite Urbano',    'url' => 'https://geoserver.meuservidor.com.br/gs/geoserver-main/PMJS/wms', 'params'   => ['LAYERS' => 'lim_urbano']]);
$this->editor->addLayer('lim_bairros',          ['type' => 'wms', 'title' => 'Limite Bairros',   'url' => 'https://geoserver.meuservidor.com.br/gs/geoserver-main/PMJS/wms', 'params'   => ['LAYERS' => 'lim_bairros']]);
$this->editor->addLayer('lim_lotes_urbanos',    ['type' => 'wms', 'title' => 'Lotes Urbanos',    'url' => 'https://geoserver.meuservidor.com.br/gs/geoserver-main/PMJS/wms', 'params'   => ['LAYERS' => 'lim_lotes_urbanos']]);

$this->editor->setShowToolbarLabels(false);     // Apenas ícones
$this->editor->setToolbarPosition('top-left');  // Muda posição

```