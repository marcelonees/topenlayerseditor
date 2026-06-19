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

$editor = new OpenLayersEditor([
    'useOLE' => true,
    'assetsPath' => 'vendor/marcelonees/topenlayerseditor/src/OpenLayersEditor/'
]);
$editor->setSize('100%', '500px');
$editor->setGeometry($geomData);

/* Escuta mudanças */
TScript::create("
    document.addEventListener('geometryChanged', function(e) {
        document.getElementById('geom_field').value = e.detail.geometry;
    });
");
```