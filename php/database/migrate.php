<?php
/**
 * Database migration script.
 * Creates missing tables (blog, translations) and inserts seed data.
 *
 * Run: php php/database/migrate.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function runMigration(): void
{
    $db = getDb();
    echo "=== Ram;Lop Database Migration ===\n\n";

    // 0. Ensure admin_users has TOTP columns (schema v1.1.0+)
    echo "--- Admin users TOTP columns ---\n";
    try {
        $db->exec("ALTER TABLE admin_users
            ADD COLUMN totp_secret  VARCHAR(255) DEFAULT NULL AFTER is_active,
            ADD COLUMN totp_enabled BOOLEAN DEFAULT FALSE AFTER totp_secret,
            ADD COLUMN backup_codes TEXT DEFAULT NULL AFTER totp_enabled");
        echo "  ✓ TOTP columns added\n";
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "  - already present, skipping\n";
        } else {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 1. Translation tables
    echo "--- Translation tables ---\n";
    $translationTables = [
        "CREATE TABLE IF NOT EXISTS product_translations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id  VARCHAR(50) NOT NULL,
            lang        CHAR(2) NOT NULL,
            name        VARCHAR(255),
            description TEXT,
            details     TEXT COMMENT 'JSON array of translated detail texts',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE KEY uk_product_lang (product_id, lang)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS category_translations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id VARCHAR(50) NOT NULL,
            lang        CHAR(2) NOT NULL,
            name        VARCHAR(100) NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            UNIQUE KEY uk_category_lang (category_id, lang)
        ) ENGINE=InnoDB",
    ];
    foreach ($translationTables as $sql) {
        try {
            $db->exec($sql);
            echo "  ✓ " . explode(' ', $sql)[5] . "\n";
        } catch (PDOException $e) {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 2. Blog tables
    echo "\n--- Blog tables ---\n";
    $blogTables = [
        "CREATE TABLE IF NOT EXISTS blog_categories (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(200) NOT NULL,
            slug        VARCHAR(200) NOT NULL UNIQUE,
            description TEXT,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title             VARCHAR(255) NOT NULL,
            slug              VARCHAR(255) NOT NULL UNIQUE,
            excerpt           TEXT,
            content           LONGTEXT NOT NULL,
            featured_image    VARCHAR(500),
            author            VARCHAR(200) DEFAULT 'Ram;Lop',
            status            ENUM('draft', 'published') DEFAULT 'draft',
            category_id       INT UNSIGNED DEFAULT NULL,
            published_at      TIMESTAMP NULL,
            created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at        TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_published (published_at),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB",
    ];
    foreach ($blogTables as $sql) {
        try {
            $db->exec($sql);
            echo "  ✓ " . explode(' ', $sql)[5] . "\n";
        } catch (PDOException $e) {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 2b. Blog posts thumbnail_image column
    echo "\n--- Blog posts thumbnail_image column ---\n";
    try {
        $db->exec("ALTER TABLE blog_posts ADD COLUMN thumbnail_image VARCHAR(500) DEFAULT NULL AFTER excerpt");
        echo "  ✓ thumbnail_image column added\n";
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "  - already present, skipping\n";
        } else {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 2c. Blog translation tables (must be after blog tables)
    echo "\n--- Blog translation tables ---\n";
    $blogTransTables = [
        "CREATE TABLE IF NOT EXISTS blog_post_translations (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            blog_post_id  INT UNSIGNED NOT NULL,
            lang          CHAR(2) NOT NULL,
            title         VARCHAR(255),
            excerpt       TEXT,
            content       LONGTEXT,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (blog_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
            UNIQUE KEY uk_post_lang (blog_post_id, lang)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS blog_category_translations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id INT UNSIGNED NOT NULL,
            lang        CHAR(2) NOT NULL,
            name        VARCHAR(200) NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE,
            UNIQUE KEY uk_bcat_lang (category_id, lang)
        ) ENGINE=InnoDB",
    ];
    foreach ($blogTransTables as $sql) {
        try {
            $db->exec($sql);
            echo "  ✓ " . explode(' ', $sql)[5] . "\n";
        } catch (PDOException $e) {
            echo "  ✗ " . $e->getMessage() . "\n";
        }
    }

    // 3. Seed category translations (EN)
    echo "\n--- Category translations (EN) ---\n";
    if (count($db->query("SELECT id FROM category_translations LIMIT 1")->fetchAll()) === 0) {
        $catStmt = $db->prepare("SELECT id, name FROM categories");
        $catStmt->execute();
        $cats = $catStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $enNames = [
            'cat-heels' => 'Heels',
            'cat-sandals' => 'Sandals',
            'cat-mules' => 'Mules',
            'cat-boots' => 'Boots',
            'cat-flats' => 'Flats',
        ];
        $insert = $db->prepare("INSERT INTO category_translations (category_id, lang, name) VALUES (?, 'en', ?)");
        $count = 0;
        foreach ($enNames as $id => $en) {
            if (isset($cats[$id])) {
                $insert->execute([$id, $en]);
                $count++;
            }
        }
        echo "  ✓ {$count} category translations inserted\n";
    } else {
        echo "  - already seeded, skipping\n";
    }

    // 4. Seed product translations (EN)
    echo "\n--- Product translations (EN) ---\n";
    if (count($db->query("SELECT id FROM product_translations LIMIT 1")->fetchAll()) === 0) {
        $translations = [
            ['prod-001', 'Architectural Stiletto Noir', 'Redefining the classic silhouette, this stiletto features sharp architectural lines and a sculpted 90mm heel. Made in Italy from premium smooth calf leather, its minimalist design eliminates unnecessary seams for a purist finish.', '["100% Calf leather exterior","Leather lining and insole","Sculptural 90mm heel","Made in Italy","Clean with soft dry cloth"]'],
            ['prod-002', 'Nude Structural Sandal', 'A sandal that embraces the foot\'s shape with clean lines and sculptural aesthetics. Its nude leather construction blends with the skin for a lengthening and sophisticated visual effect.', '["100% Calf leather","Leather sole","Adjustable buckle in gold metal","Block heel 60mm","Handmade in Spain"]'],
            ['prod-003', 'Classic Pointed Pump', 'The ultimate pump for the power woman. Elongated silhouette with pointed toe and 85mm heel. Crafted from calf leather for a perfect fit and timeless elegance.', '["Italian calf leather","Lambskin lining","85mm stiletto heel","Leather sole with insignia","Made in Italy"]'],
            ['prod-004', 'Geometric Block Mule', 'An architectural statement mule. Its sculpted block heel and minimalist silhouette make it the centerpiece of any outfit. Crafted in high-resistance black leather.', '["Black calf leather","70mm geometric block heel","Engraved rubber sole","20mm concealed platform","Made in Portugal"]'],
            ['prod-005', 'Minimal Kitten Heel', 'Discreet elegance with a 50mm kitten heel that lengthens the silhouette without sacrificing comfort. Its clean design and pristine white leather make it a collection essential.', '["White calf leather","50mm kitten heel","Rounded toe","Natural leather lining","Made in Italy"]'],
            ['prod-006', 'Architectural Cage Sandal', 'A sculptural sandal that wraps the foot in fine calf leather straps. The geometric cage design evokes architectural frameworks while the stiletto heel anchors the composition.', '["Calf leather straps","Adjustable ankle buckle","90mm stiletto heel","Leather sole","Made in Italy"]'],
            ['prod-007', 'Sculptural Block Bootie', 'An architectural bootie with a sculptural block heel and clean silhouette. Crafted in structured calf leather, its minimalist aesthetic balances volume and precision.', '["Structured calf leather","Inner zip closure","Sculptural 60mm block heel","Leather lining","Made in Portugal"]'],
        ];
        $insert = $db->prepare("INSERT INTO product_translations (product_id, lang, name, description, details) VALUES (?, 'en', ?, ?, ?)");
        $count = 0;
        foreach ($translations as $t) {
            $insert->execute($t);
            $count++;
        }
        echo "  ✓ {$count} product translations inserted\n";
    } else {
        echo "  - already seeded, skipping\n";
    }

    // 5. Seed blog categories
    echo "\n--- Blog categories ---\n";
    if (count($db->query("SELECT id FROM blog_categories LIMIT 1")->fetchAll()) === 0) {
        $db->exec("INSERT INTO blog_categories (name, slug, description) VALUES
            ('Tendencias', 'tendencias', 'Últimas tendencias en calzado artesanal'),
            ('Cuidados', 'cuidados', 'Guías para el cuidado de tus zapatos'),
            ('Detrás del Diseño', 'detras-del-diseno', 'Historias del proceso creativo y artesanal')");
        echo "  ✓ 3 blog categories inserted\n";
    } else {
        echo "  - already seeded, skipping\n";
    }

    // 6. Seed blog posts
    echo "\n--- Blog posts ---\n";
    $blogCount = (int)$db->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    if ($blogCount === 0) {
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
                'status' => 'published',
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
                'status' => 'published',
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
                'status' => 'published',
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
                'status' => 'published',
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
                $p['status'],
            ]);
        }
        echo "  ✓ 4 blog posts inserted\n";
    } else {
        echo "  - {$blogCount} posts exist, skipping\n";
    }

    // 7. Seed sample customer
    echo "\n--- Sample customer ---\n";
    $cst = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ((int)$cst === 0) {
        $db->exec("INSERT INTO customers (email, name, phone, is_verified) VALUES
            ('maria.garcia@example.com', 'María García', '+52 55 1234 5678', TRUE)");
        echo "  ✓ 1 customer inserted\n";
    } else {
        echo "  - {$cst} customers exist, skipping\n";
    }

    // 7. Seed sample orders
    echo "\n--- Sample orders ---\n";
    $ords = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    if ($ords === 0) {
        // Order 1 — Stripe, paid
        $db->exec("INSERT INTO orders (order_number, customer_id, customer_email, customer_name, customer_phone,
            shipping_name, shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
            billing_name, billing_line1, billing_city, billing_state, billing_zip, billing_country,
            subtotal, shipping_total, tax_total, discount_total, total, currency,
            status_id, payment_method_id, payment_status_id, stripe_payment_intent_id,
            paid_at, created_at)
        VALUES
            ('ORD-2026-001', 1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678',
             'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
             'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
             850.00, 50.00, 0, 0, 900.00, 'USD',
             2, 1, 2, 'pi_stripe_sample_001',
             NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY)");

        $db->exec("INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
            (1, 'prod-001', 1, 'Mule Arquitectónica', 'mule-arquitectonica', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'MULE-NOIR-36', 1, 520.00, 520.00, 'Noir', '36'),
            (1, 'prod-002', 11, 'Tacón Estructura', 'tacon-estructura', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 330.00, 'TACON-BLANC-36', 1, 330.00, 330.00, 'Blanc Cassé', '36')");

        $db->exec("INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
            (1, NULL, 1, 'Order created by customer', NOW() - INTERVAL 3 DAY),
            (1, 1, 2, 'Payment confirmed via Stripe', NOW() - INTERVAL 3 DAY)");

        $db->exec("INSERT INTO stock_movements (variant_id, quantity_change, stock_before, stock_after, reference_type, reference_id, notes, created_at) VALUES
            (1, -1, 10, 9, 'order', 'ORD-2026-001', 'Order item: Mule Arquitectónica Noir/36', NOW() - INTERVAL 3 DAY),
            (11, -1, 10, 9, 'order', 'ORD-2026-001', 'Order item: Tacón Estructura Blanc Cassé/36', NOW() - INTERVAL 3 DAY)");

        // Order 2 — Transfer, pending
        $db->exec("INSERT INTO orders (order_number, customer_id, customer_email, customer_name, customer_phone,
            shipping_name, shipping_line1, shipping_city, shipping_state, shipping_zip, shipping_country,
            billing_name, billing_line1, billing_city, billing_state, billing_zip, billing_country,
            subtotal, shipping_total, tax_total, discount_total, total, currency,
            status_id, payment_method_id, payment_status_id, notes, created_at)
        VALUES
            ('ORD-2026-002', 1, 'maria.garcia@example.com', 'María García', '+52 55 1234 5678',
             'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
             'María García', 'Av. Reforma 123, Col. Juárez', 'Ciudad de México', 'CDMX', '06600', 'MX',
             520.00, 50.00, 0, 0, 570.00, 'USD',
             1, 2, 1, 'Cliente pagará por transferencia bancaria',
             NOW() - INTERVAL 1 DAY)");

        $db->exec("INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_slug, product_image, product_price, product_sku, quantity, unit_price, subtotal, selected_color, selected_size) VALUES
            (2, 'prod-007', 45, 'Botín Bloque Escultural', 'botin-bloque-escultural', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&q=80', 520.00, 'BOTIN-NOIR-36', 1, 520.00, 520.00, 'Noir', '36')");

        $db->exec("INSERT INTO order_status_history (order_id, from_status_id, to_status_id, notes, created_at) VALUES
            (2, NULL, 1, 'Order created by customer via bank transfer', NOW() - INTERVAL 1 DAY)");

        echo "  ✓ 2 sample orders inserted\n";
    } else {
        echo "  - {$ords} orders exist, skipping\n";
    }

    echo "\n=== Migration complete! ===\n";
}

try {
    runMigration();
} catch (PDOException $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
