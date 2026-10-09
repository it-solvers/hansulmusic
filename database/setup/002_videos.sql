-- [1단계] Concert 페이지와 YouTube 페이지가 함께 사용하는 영상 테이블을 만들고 초기 자료를 등록합니다.
-- is_active=1인 영상만 각 페이지와 홈페이지에 표시하고, is_active=0인 영상은 숨기되 행은 유지합니다.
CREATE TABLE videos (
    video_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '영상 ID',
    video_type TINYINT(1) NOT NULL COMMENT '표시 페이지: 1=Concert, 2=YouTube',
    role TINYINT(1) NOT NULL COMMENT '영상 분류: 1=Compositions, 2=Music Arranged',
    video_title VARCHAR(255) NULL COMMENT '영상 제목',
    video_url VARCHAR(500) NOT NULL COMMENT 'YouTube 영상 URL',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '표시 순서',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '표시 상태: 1=표시, 0=숨김(행 유지)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 시각',
    PRIMARY KEY (video_id),
    KEY idx_videos_display (video_type, role, is_active, sort_order, video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Concert 및 YouTube 영상 목록';

INSERT INTO videos (video_type, role, video_url, sort_order, is_active) VALUES
    (1, 1, 'https://youtu.be/GWMxZGTX89k', 1, 1),
    (1, 1, 'https://youtu.be/np22tH6c3ZQ', 2, 1),
    (1, 1, 'https://youtu.be/QuXcW1VGa6o', 3, 1),
    (1, 1, 'https://youtu.be/SS3pXIHGl1g', 4, 1),
    (1, 1, 'https://youtu.be/FcvnPlXvefI', 5, 1),
    (1, 1, 'https://youtu.be/UCYMDVun2X0', 6, 1),
    (1, 1, 'https://youtu.be/4LVlBw8W3bs', 7, 1),
    (1, 1, 'https://youtu.be/_Bx0jd5-Jho', 8, 1),
    (1, 1, 'https://youtu.be/lPcXQ2XhF44', 9, 1),
    (1, 1, 'https://youtu.be/m_ispYkGQ3E', 10, 1),
    (1, 1, 'https://youtu.be/qkD_Z73Zc50', 11, 1),
    (1, 1, 'https://youtu.be/KuHl0gqcst8', 12, 1),
    (1, 1, 'https://youtu.be/4iKAPAGdR_o', 13, 1),
    (1, 1, 'https://youtu.be/0PUKrtNwshc', 14, 1),
    (1, 1, 'https://youtu.be/5ejapgFA2dQ', 15, 1),
    (1, 1, 'https://youtu.be/jeCSSCV9ITM', 16, 1),
    (1, 1, 'https://youtu.be/Pk-Mstdcgu0', 17, 1),
    (1, 1, 'https://youtu.be/Ka2-3PeuECU', 18, 1),
    (1, 1, 'https://youtu.be/I9CpRfG-Qgo', 19, 1);

INSERT INTO videos (video_type, role, video_title, video_url, sort_order, is_active) VALUES
    (2, 1, '그 사랑 알게하소서 Let Me Know Your Love (with English Lyrics) Performance', 'https://youtu.be/VwpP-MTFex4', 1, 1),
    (2, 1, '가는 길 The Way I Am Going (Performance)', 'https://youtu.be/vVDwVAxcj8I', 2, 1),
    (2, 1, '나를 향한 주의 사랑 God''s Love Toward Me (English Lyrics_Performance)', 'https://youtu.be/NidJqtBl4Bc', 3, 1),
    (2, 1, '성령의 꽃 Holy Spirit Flower (with English Lyrics_Performance)', 'https://youtu.be/dqOQL61BWI0', 4, 1);

INSERT INTO videos (video_type, role, video_title, video_url, sort_order, is_active) VALUES
    (2, 1, '나의 찬양 My Tribute / To God Be The Glory (English-Korean Lyrics_Performance)', 'https://youtu.be/j_C8GUvrNso', 5, 1),
    (2, 1, '보혈의 십자가 The Cross of the Precious Blood (Performance Part)', 'https://youtu.be/iT9CVTiBv4M', 6, 1),
    (2, 1, '빛으로 오신 나의 주 My Lord Who Came as Light (English Lyrics_Chorus Part)', 'https://youtu.be/R58dh3jXUoA', 7, 1),
    (2, 1, '나의 기도 My Prayer (Performance Part)', 'https://youtu.be/pnyjYoTzDH0', 8, 1),
    (2, 1, '은혜의 주를 찬양해 Praise the Lord of Grace (Performance)', 'https://youtu.be/ZxVIsZRQT2s', 9, 1),
    (2, 1, '이 세상 끝날 까지 Angel''s Story', 'https://youtu.be/Z5ZgWqHcNEg', 10, 1),
    (2, 1, '그리움 (Nostalgic Sweetness)', 'https://youtu.be/g8Ytuu7WUoU', 11, 1),
    (2, 1, '주의 은혜 안에서 In the Grace of God (English Lyrics_Performance)', 'https://youtu.be/ze7y9jIlCKE', 12, 1),
    (2, 1, '주님을 찬양하라 Praise The Lord (Performance Part)', 'https://youtu.be/MkKo6-WYags', 13, 1),
    (2, 1, '주님의 십자가 The Cross of the Lord (Performance)', 'https://youtu.be/lu1QxewMecQ', 14, 1),
    (2, 1, '그리움 Nostalgic Sweetness (with English Lyrics Score)', 'https://youtu.be/q653qgxuGOQ', 15, 1),
    (2, 1, '주의 사랑 Love of My Lord (Performance)', 'https://youtu.be/0BakAcaFGC0', 16, 1),
    (2, 1, '거룩 Sanctus (Performance)', 'https://youtu.be/j4qYmD1kZZQ', 17, 1),
    (2, 1, '마지막 사랑 The Last Love', 'https://youtu.be/pBR5XN5awaQ', 18, 1),
    (2, 1, '전능하신 주를 찬양해 Praise The Mighty God (Performance)', 'https://youtu.be/DfXYNiaiEzA', 19, 1),
    (2, 1, 'Scenery 경치 for Piano Solo', 'https://youtu.be/jFdLz9V7yYc', 20, 1),
    (2, 1, '시편23편 Psalms 23', 'https://youtu.be/_lgaC1KMxzI', 21, 1),
    (2, 1, '파이어스 PIOUS단가 (Chorus Part)', 'https://youtu.be/Jegcv3a3FwA', 22, 1),
    (2, 1, '피아노를 위한 환상곡 Fantasia for Piano Solo', 'https://youtu.be/nkOp4VZtDOk', 23, 1),
    (2, 1, '바이올린과 첼로를 위한 진혼곡 Requiem for Violin and Cello', 'https://youtu.be/ripELPFLBHw', 24, 1),
    (2, 2, '크리스마스 칸타타, "왕이오신다" (Michael E. Parks) "A KING COMES," Christmas Cantata', 'https://youtu.be/9KpD5WtcGnc', 25, 1),
    (2, 2, '광주팝스합창단 아침이슬 창단연주회 공연실황 (2023년 12월 2일 국립아시아문화전당)', 'https://youtu.be/MRMcjcIOcTw', 26, 1),
    (2, 2, '임을 위한 행진곡 광주팝스합창단 창단연주회 공연 실황 (2023년 12월 2일 국립아시아문화전당)', 'https://youtu.be/nfwe3OZCn28', 27, 1),
    (2, 2, 'Jazz Live: I SEE 흥! (with 시흥시음악협회)', 'https://youtu.be/_auDi-7oltY', 28, 1),
    (2, 1, '가을의 기도 Autumn Prayer', 'https://youtu.be/EtXdbuZdMck', 29, 1),
    (2, 1, '토우디엔씨의 노래 Anthem of Tow DNC (Piano Version)', 'https://youtu.be/cRQM_wVQQ20', 30, 1),
    (2, 1, '"Landscape (풍경)" for Piano Solo', 'https://youtu.be/0fTAFGd-czM', 31, 1),
    (2, 1, '토우디엔씨의 노래 Anthem of Tow DNC (Orchestra Version)', 'https://youtu.be/9HsGIFBAbDY', 32, 1);
