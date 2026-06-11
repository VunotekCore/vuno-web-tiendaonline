<?php
/**
 * Seed blog posts with 4 default fashion/lifestyle articles.
 * Safe to run multiple times — skips if posts already exist.
 *
 * Run: php php/database/seed-blog.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$db = getDb();
echo "=== Ram;Lop Blog Seed ===\n\n";

// 1. Ensure thumbnail_image column exists
echo "--- Checking thumbnail_image column ---\n";
try {
    $db->exec("ALTER TABLE blog_posts ADD COLUMN thumbnail_image VARCHAR(500) DEFAULT NULL AFTER excerpt");
    echo "  ✓ Column added\n";
} catch (\PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "  - already exists, skipping\n";
    } else {
        echo "  ✗ " . $e->getMessage() . "\n";
    }
}

// 2. Seed blog categories if empty
echo "\n--- Blog categories ---\n";
if ((int)$db->query("SELECT COUNT(*) FROM blog_categories")->fetchColumn() === 0) {
    $db->exec("INSERT INTO blog_categories (name, slug, description) VALUES
        ('Tendencias', 'tendencias', 'Últimas tendencias en calzado artesanal'),
        ('Cuidados', 'cuidados', 'Guías para el cuidado de tus zapatos'),
        ('Detrás del Diseño', 'detras-del-diseno', 'Historias del proceso creativo y artesanal')");
    echo "  ✓ 3 categories inserted\n";
} else {
    echo "  - already exist, skipping\n";
}

// 3. Seed 4 blog posts
echo "\n--- Blog posts ---\n";
if ((int)$db->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn() > 0) {
    echo "  - posts already exist, skipping\n";
    echo "\n=== Done ===\n";
    exit(0);
}

$posts = [
    [
        'title' => '5 Tendencias de Moda que Definirán Esta Temporada',
        'slug' => 'tendencias-moda-temporada',
        'excerpt' => 'Descubre las cinco tendencias imprescindibles que están marcando el rumbo de la moda: desde el calzado escultural hasta los accesorios que roban miradas.',
        'content' => '<h2>El Regreso del Minimalismo Arquitectónico</h2>
<p>Esta temporada, la moda abraza la pureza de las líneas limpias y las siluetas depuradas. Los diseñadores apuestan por formas escultóricas que recuerdan a la arquitectura brutalista, con bloques geométricos y tacones que desafían la gravedad. El calzado artesanal se convierte en el centro de atención, con piezas que funcionan casi como instalaciones de arte portable.</p>
<p>Los materiales nobles como el cuero vacuno, la piel de becerro y los acabados satinados dominan las colecciones. Los colores tierra, el negro monólito y los tonos nude se consolidan como la paleta esencial del armario consciente.</p>

<h2>La Cartera Estructurada</h2>
<p>Olvídate de los bolsos flácidos. Esta temporada la cartera estructurada es la protagonista indiscutible. Piezas de líneas rectas, asas superiores y cierres minimalistas que complementan cualquier look con un toque de sofisticación arquitectónica. Piensa en formas geométricas: rectángulos precisos, semicírculos perfectos y cubos suaves que desafían lo convencional.</p>
<p>Los colores estrella son el blanco roto, el negro profundo y el camel, aunque también vemos propuestas en tonos joya como el esmeralda y el zafiro para quienes buscan un punto de color estratégico.</p>

<h2>Tejidos Artesanales con Consciencia</h2>
<p>La artesanía textil vive un renacimiento. Tejidos como el crochet, el macramé y los bordados hechos a mano se integran en prendas y accesorios con una estética contemporánea. La tendencia valora el proceso tanto como el resultado final, celebrando las imperfecciones que hacen única cada pieza.</p>
<p>Los zapatos artesanales con detalles tejidos, las carteras de fibras naturales y los cinturones trenzados a mano son algunas de las piezas clave que no pueden faltar en tu colección esta temporada.</p>

<h2>La Silueta Oversized Reimaginada</h2>
<p>El volumen se reinventa. Chaquetas con hombros marcados, pantalones de pierna ancha y faldas midi con caída fluida conforman una silueta que equilibra estructura y movimiento. La clave está en contrastar volúmenes: una parte superior holgada combinada con una falda lápiz ajustada, o un pantalón ancho con un top ceñido.</p>
<p>Para los zapatos, la silueta oversized se complementa perfectamente con tacones kitten o bloques geométricos que asoman bajo el dobladillo, creando un diálogo visual entre el volumen de la prenda y la precisión del calzado.</p>

<h2>Accesorios como Punto Focal</h2>
<p>Esta temporada, menos es más, pero ese "menos" debe ser extraordinario. La tendencia apuesta por un único accesorio protagonista que lleva todo el peso del look: unos pendientes esculturales, un cinturón arquitectónico o, por supuesto, un par de zapatos artesanales que son en sí mismos una declaración de intenciones.</p>
<p>La inversión en piezas de calidad, hechas a mano y con materiales nobles, no solo es una elección estética sino también ética. La moda lenta apuesta por la durabilidad, la atemporalidad y el respeto por el trabajo artesanal.</p>',
        'featured_image' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=1200&q=80',
        'thumbnail_image' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=600&q=80',
        'author' => 'María Ram;Lop',
        'category_id' => 1,
        'published_at' => 'NOW() - INTERVAL 7 DAY',
    ],
    [
        'title' => 'Guía Completa para el Cuidado de Tus Zapatos Artesanales',
        'slug' => 'guia-cuidado-zapatos-artesanales',
        'excerpt' => 'Aprende a preservar la belleza y durabilidad de tus zapatos hechos a mano con nuestra guía experta de cuidados, limpieza y almacenamiento.',
        'content' => '<h2>Por Qué el Cuidado es Esencial</h2>
<p>Un par de zapatos artesanales es una inversión en calidad, diseño y sostenibilidad. A diferencia de la producción industrial, cada par hecho a mano pasa por decenas de horas de trabajo minucioso: desde el corte del cuero hasta el cosido de la suela. Cuidarlos adecuadamente no solo prolonga su vida útil, sino que honra el trabajo del artesano que los creó.</p>
<p>Con los cuidados apropiados, unos zapatos artesanales de buena calidad pueden durar décadas. Aquí te compartimos nuestra guía completa para mantenerlos como el primer día.</p>

<h2>Limpieza Según el Material</h2>
<h3>Cuero Liso</h3>
<p>El cuero liso es el material más común en el calzado artesanal. Para limpiarlo, sigue estos pasos:</p>
<ul>
<li>Retira el polvo superficial con un cepillo de cerdas suaves o un paño de microfibra seco.</li>
<li>Prepara una solución de agua tibia con unas gotas de jabón neutro (pH balanceado).</li>
<li>Humedece ligeramente un paño suave y pásalo sobre la superficie con movimientos circulares.</li>
<li>No sumerjas los zapatos en agua ni los mojes en exceso.</li>
<li>Seca al aire libre, lejos de fuentes de calor directo como radiadores o luz solar intensa.</li>
</ul>

<h3>Cuero Grabado o Texturizado</h3>
<p>Los cueros con textura requieren un cuidado especial. Usa un cepillo de cerdas naturales para llegar a los rincones del grabado. Evita los limpiadores líquidos que podrían acumularse en las texturas. Un paño ligeramente humedecido con agua y jabón neutro es suficiente.</p>

<h2>Hidratación y Nutrición</h2>
<p>El cuero es una piel natural que necesita hidratarse para mantener su flexibilidad y evitar grietas. Aplica un acondicionador de cuero de calidad cada 2-3 meses, o cada vez que notes el material seco al tacto. Los productos con cera de abeja, lanolina o aceites naturales son excelentes opciones.</p>
<p><strong>Consejo profesional:</strong> Prueba siempre el producto en una zona pequeña y discreta antes de aplicarlo en toda la superficie.</p>

<h2>Almacenamiento Correcto</h2>
<p>La forma en que guardas tus zapatos artesanales impacta directamente en su durabilidad:</p>
<ul>
<li>Usa hormas de madera de cedro para mantener la forma, absorber la humedad y prevenir olores.</li>
<li>Guarda los zapatos en bolsas de tela transpirable (nunca en plástico).</li>
<li>Mantenlos en un lugar fresco y seco, lejos de la luz solar directa.</li>
<li>Alterna el uso de tus zapatos: nunca uses el mismo par dos días seguidos. El cuero necesita al menos 24 horas para recuperar su forma y respirar.</li>
</ul>

<h2>Cuándo Acudir a un Profesional</h2>
<p>Aunque el cuidado diario puede hacerse en casa, ciertos trabajos requieren la mano de un experto. Lleva tus zapatos a un zapatero profesional para:</p>
<ul>
<li>Cambio de suelas y medias suelas.</li>
<li>Reparación de costuras o grietas profundas.</li>
<li>Teñido o retoque de color.</li>
<li>Colocación de protectores de suela (recomendado antes del primer uso).</li>
</ul>
<p>Un buen zapatero no solo reparará el daño, sino que puede aconsejarte sobre cuidados específicos para cada par.</p>',
        'featured_image' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=1200&q=80',
        'thumbnail_image' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&q=80',
        'author' => 'Ram;Lop',
        'category_id' => 2,
        'published_at' => 'NOW() - INTERVAL 4 DAY',
    ],
    [
        'title' => 'El Proceso Artesanal: De la Inspiración al Calzado',
        'slug' => 'proceso-artesanal-inspiracion-calzado',
        'excerpt' => 'Te llevamos detrás del taller para mostrarte cómo nace cada diseño: desde el boceto inicial hasta el último punto de costura en nuestras piezas artesanales.',
        'content' => '<h2>La Chispa Creativa</h2>
<p>Todo comienza con una imagen mental. Para nuestros diseñadores, la inspiración puede surgir de cualquier lugar: la curva de un edificio brutalista, el pliegue de una tela al caer, la textura de una piedra erosionada por el tiempo. La arquitectura minimalista es nuestra musa constante: líneas rectas, volúmenes precisos, espacios vacíos que respiran.</p>
<p>El proceso creativo es iterativo. Boceto tras boceto, vamos depurando la forma hasta encontrar la silueta que encapsula la visión inicial. No hay prisas en esta etapa; la paciencia es parte del oficio.</p>

<h2>La Selección del Material</h2>
<p>Elegir el cuero adecuado es una de las decisiones más importantes en la creación de un zapato artesanal. Trabajamos con curtidurías históricas de Italia, España y Portugal que comparten nuestra filosofía de calidad sobre cantidad. Cada piel se selecciona a mano, buscando la textura, el grosor y la elasticidad perfectos para cada diseño.</p>
<p>Los colores se tiñen en pequeños lotes, logrando tonos profundos y uniformes que envejecen con dignidad. Un cuero de calidad no solo se ve mejor, sino que se moldea al pie con el uso, creando una calzada única e irrepetible.</p>

<h2>El Corte: Precisión Milimétrica</h2>
<p>Con el patrón en mano y la piel seleccionada, comienza el corte. Cada pieza se corta individualmente, maximizando el uso del material y respetando la dirección natural de la fibra del cuero. Un corte preciso es la base de un zapato que calza perfectamente.</p>
<p>Nuestros artesanos utilizan cuchillas tradicionales y patrones de metal que se han perfeccionado a lo largo de años de prueba y error. La presión exacta, el ángulo del corte y la velocidad son factores que solo la experiencia puede enseñar.</p>

<h2>El Armado: Donde el Calzado Cobra Vida</h2>
<p>Esta es quizás la etapa más mágica del proceso. Sobre la horma —esa forma de madera o plástico que imita la anatomía del pie— se montan las piezas cortadas. El cuero se estira, se moldea, se clava y se pega con una precisión que roza lo quirúrgico.</p>
<p>Es en esta fase donde el zapato adquiere su personalidad. La horma define no solo la talla, sino la silueta completa: la altura del empeine, la curva del talón, el ángulo de la puntera. Cada modelo tiene su horma específica, creada y esculpida a medida.</p>

<h2>La Suela y el Acabado</h2>
<p>La suela es el fundamento del zapato. Para nuestros diseños, combinamos suelas de cuero tradicionales con suelas de caucho grabado que ofrecen tracción sin sacrificar la estética. Cada suela se cose a mano al cuerpo del zapato con hilo encerado, una técnica que garantiza durabilidad y permite reemplazar la suela cuando sea necesario.</p>
<p>El acabado final incluye el pulido de los bordes, la aplicación de tintes y ceras protectoras, y una inspección minuciosa de cada costura. Ningún par sale del taller sin haber pasado por al menos tres controles de calidad independientes.</p>

<h2>El Toque Final</h2>
<p>Antes de empaquetar, cada zapato se cepilla, se hidrata ligeramente y se protege con un paño de algodón. Se colocan las hormas para mantener la forma durante el transporte. Se firma cada par con un sello que indica el número de serie, la talla y las iniciales del artesano que lo creó.</p>
<p>Ese pequeño detalle —las iniciales del artesano— es nuestro recordatorio de que detrás de cada par hay una persona, sus manos, su experiencia y su pasión por un oficio que se niega a desaparecer.</p>',
        'featured_image' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=1200&q=80',
        'thumbnail_image' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=600&q=80',
        'author' => 'Carlos Mendoza',
        'category_id' => 3,
        'published_at' => 'NOW() - INTERVAL 1 DAY',
    ],
    [
        'title' => 'Cómo Combinar tus Zapatos con los Accesorios Perfectos',
        'slug' => 'como-combinar-zapatos-accesorios',
        'excerpt' => 'Dominar el arte de coordinar calzado, carteras y accesorios puede transformar cualquier look. Te compartimos las claves para crear combinaciones armónicas y sofisticadas.',
        'content' => '<h2>La Regla de Oro: Menos es Más</h2>
<p>En el mundo de la moda consciente, la máxima de "menos es más" sigue más vigente que nunca. Pero aplicarla correctamente requiere entender los principios básicos de coordinación entre calzado, carteras y accesorios. No se trata de usar todo a juego, sino de crear un diálogo visual armónico entre las piezas.</p>
<p>La clave está en elegir un punto focal y construir alrededor de él. Si tus zapatos son la pieza protagonista —como un par de mules arquitectónicas en negro—, el resto de accesorios deben acompañar sin competir.</p>

<h2>Zapato + Cartera: La Combinación Clásica</h2>
<p>La combinación de zapatos y cartera es la más visible de todo el look. Aquí tienes algunas reglas prácticas:</p>
<h3>Tono sobre Tono</h3>
<p>La opción más segura y elegante. Combina tus zapatos con la cartera en el mismo tono o en tonos muy cercanos de la misma familia cromática. Un zapato camel con una cartera caramelo, o un zapato negro con una cartera en gris oscuro. Esta técnica alarga visualmente la silueta y proyecta una imagen pulida y sofisticada.</p>
<h3>Contraste Controlado</h3>
<p>Si prefieres un look más dinámico, opta por el contraste. Un zapato blanco roto con una cartera en un tono vibrante como el burdeos o el azul marino. La clave está en que los colores contrastantes compartan la misma intensidad o saturación para que el resultado sea armónico.</p>
<h3>Textura como Vínculo</h3>
<p>Cuando los colores no coinciden exactamente, la textura puede ser el puente visual. Un zapato de cuero liso combina maravillosamente con una cartera de cuero grabado en un tono complementario. La diferencia de texturas añade profundidad al conjunto sin romper la armonía.</p>

<h2>El Papel de los Metales</h2>
<p>Los accesorios metálicos —hebillas, cremalleras, cierres, joyas— deben seguir una regla simple pero efectiva: elige una temperatura de metal y mantenla. Oro con oro, plata con plata. Mezclar temperaturas metálicas es uno de los errores más comunes y el que más desentona en un look cuidado.</p>
<p>Si tus zapatos tienen hebillas doradas, tus pendientes deberían ser dorados, tu reloj dorado y los herrajes de tu cartera dorados también. Esta coherencia crea un hilo visual que unifica todo el conjunto.</p>

<h2>Accesorios que Suman, No que Restan</h2>
<p>Además de la cartera, hay otros accesorios que merecen atención:</p>
<ul>
<li><strong>Cinturón:</strong> Debe coordinarse con los zapatos, ya sea en color (si es posible) o al menos en temperatura de hebilla. Un cinturón ancho sobre un vestido fluido crea una silueta interesante.</li>
<li><strong>Pañuelos y bufandas:</strong> Son excelentes para añadir un toque de color o textura. Un pañuelo de seda atado al cuello o a la cartera puede ser el detalle que eleve todo el look.</li>
<li><strong>Joyas:</strong> Menos es más. Elige una pieza statement (unos pendientes arquitectónicos, un brazalete escultórico) y mantén el resto mínimo. Las joyas deben realzar, no abrumar.</li>
</ul>

<h2>Adapta las Reglas a Tu Estilo</h2>
<p>Al final, la moda es una expresión personal. Estas guías son puntos de partida, no reglas inflexibles. Si sientes que una combinación funciona, probablemente funciona. La confianza con la que llevas un look es más importante que cualquier regla de estilo.</p>
<p>Experimenta, prueba combinaciones inesperadas, y descubre qué funciona para ti. La moda artesanal te da herramientas de calidad; tú pones la personalidad.</p>',
        'featured_image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=1200&q=80',
        'thumbnail_image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&q=80',
        'author' => 'Ram;Lop',
        'category_id' => 1,
        'published_at' => 'NOW()',
    ],
];

foreach ($posts as $p) {
    $sql = "INSERT INTO blog_posts (title, slug, excerpt, thumbnail_image, content, featured_image, author, category_id, status, published_at, created_at)
            VALUES (" . implode(',', array_fill(0, 9, '?')) . ", {$p['published_at']}, NOW())";
    $db->prepare($sql)->execute([
        $p['title'],
        $p['slug'],
        $p['excerpt'],
        $p['thumbnail_image'],
        $p['content'],
        $p['featured_image'],
        $p['author'],
        $p['category_id'],
        'published',
    ]);
}

echo "  ✓ 4 blog posts inserted\n";
echo "\n=== Done ===\n";
