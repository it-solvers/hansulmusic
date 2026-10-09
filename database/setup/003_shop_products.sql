-- [1단계] 상점에서 사용하는 상품과 상품별 미리보기 자산 테이블을 준비합니다.
-- 상품 및 자산 행은 관리 화면에서 물리 삭제하지 않고, 상품은 is_active로 진열 여부를 관리합니다.
-- 이미지·음원 파일 자체는 DB가 아니라 서버 파일 시스템에 보관합니다.
-- [2단계] product_type이 상품 종류(1=악보, 2=음원)이며 종류마다 한 개의 상품만 등록합니다(UNIQUE).
-- 관리 화면(admin-products.php)의 상품 종류 선택과 영문 상점·홈의 번역 문구(product-localization.php)도 이 값을 사용합니다.
CREATE TABLE products (
    product_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '상품 ID',
    product_type TINYINT UNSIGNED NOT NULL COMMENT '상품 종류: 1=악보(Scores), 2=음원(Records). 종류마다 한 개만 등록',
    name VARCHAR(255) NOT NULL COMMENT '상품명',
    subtitle VARCHAR(255) NOT NULL DEFAULT '' COMMENT '상품 부제',
    description TEXT NOT NULL COMMENT '상품 설명',
    regular_price_krw INT UNSIGNED NOT NULL COMMENT '정가(원)',
    sale_price_krw INT UNSIGNED NOT NULL COMMENT '할인가(원)',
    cover_path VARCHAR(512) NULL COMMENT '표지 이미지 경로',
    preview_audio_path VARCHAR(512) NULL COMMENT '미리듣기 음원 경로',
    download_path VARCHAR(512) NOT NULL COMMENT '다운로드 파일 경로',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '상점 진열 상태: 1=진열, 0=숨김(상품 행은 유지)',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '상점 진열 순서(작을수록 먼저 표시)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 시각',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시각',
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_products_type (product_type),
    KEY idx_products_display (is_active, product_type, sort_order, product_id),
    CONSTRAINT chk_products_type CHECK (product_type IN (1, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='악보와 음원 상품 정보';

-- [2단계] 악보 상품의 페이지별 미리보기 이미지 경로·설명·표시 순서를 저장합니다.
CREATE TABLE product_assets (
    asset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '파일 정보 ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '상품 ID',
    asset_type ENUM('preview_image') NOT NULL COMMENT '파일 종류',
    asset_path VARCHAR(512) NOT NULL COMMENT '미리보기 파일 경로',
    label VARCHAR(255) NOT NULL DEFAULT '' COMMENT '미리보기 설명',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '표시 순서',
    PRIMARY KEY (asset_id),
    KEY idx_product_assets_display (product_id, asset_type, sort_order, asset_id),
    -- 상품을 DB에서 직접 삭제할 경우 연결된 자산 행도 함께 삭제됩니다.
    -- 관리자 화면에서는 상품 삭제 기능을 제공하지 않고 is_active=0으로 숨깁니다.
    CONSTRAINT fk_product_assets_product
        FOREIGN KEY (product_id) REFERENCES products (product_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='상품 미리보기 파일 경로';

-- [3단계] 초기 악보·음원 상품을 등록합니다. is_active=1이면 한글·영문 상점에 표시됩니다.
INSERT INTO products (
    product_type, name, subtitle, description,
    regular_price_krw, sale_price_krw, cover_path,
    preview_audio_path, download_path, is_active, sort_order
) VALUES
    (
        1, '8.1.3. 칸타타 영광영광',
        'Orchestra Score and Parts', '관현악 악보 일부를 미리 확인할 수 있습니다.',
        300000, 100000, NULL, NULL,
        'product-media/scoreszip/score-glory-preview.zip', 1, 1
    ),
    (
        2, '바람이 불었으면 좋겠어',
        '길구봉구 · MP3', '음원을 미리 들어보세요. 미리듣기는 처음 1분 제공됩니다.',
        1000, 800, NULL,
        'product-media/music/길구봉구-01-바람이 불었으면 좋겠어.mp3',
        'product-media/music/길구봉구-01-바람이 불었으면 좋겠어.mp3', 1, 2
    );

-- [3단계] product_type으로 해당 악보의 product_id를 조회해 페이지별 미리보기 파일을 연결합니다.
-- 아래 조회 결과는 이후 product_assets 조회 시 상품 카드 팝업의 페이지 순서대로 표시됩니다.
INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
SELECT product_id, 'preview_image', 'product-media/scores/8.1.3. 칸타타 영광영광-08.png', '8쪽', 1
FROM products WHERE product_type = 1;

-- 같은 악보 상품에 다음 미리보기 페이지를 순서대로 추가합니다.
INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
SELECT product_id, 'preview_image', 'product-media/scores/8.1.3. 칸타타 영광영광-09.png', '9쪽', 2
FROM products WHERE product_type = 1;

-- 같은 악보 상품에 다음 미리보기 페이지를 순서대로 추가합니다.
INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
SELECT product_id, 'preview_image', 'product-media/scores/8.1.3. 칸타타 영광영광-10.png', '10쪽', 3
FROM products WHERE product_type = 1;
