<?php

if (!function_exists('normalize_pest_key')) {
    function normalize_pest_key($name) {
        $normalized = strtolower(trim((string)$name));
        $normalized = str_replace(array('-', '_', '/'), ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }
}

if (!function_exists('translate_pest_name_vi')) {
    function translate_pest_name_vi($name) {
        $key = normalize_pest_key($name);

        $exact_map = array(
            'brown planthopper' => 'Rầy nâu',
            'bph' => 'Rầy nâu',
            'white backed planthopper' => 'Rầy lưng trắng',
            'wbph' => 'Rầy lưng trắng',
            'small brown planthopper' => 'Rầy nâu nhỏ',
            'sbph' => 'Rầy nâu nhỏ',
            'green leafhopper' => 'Rầy xanh',
            'glh' => 'Rầy xanh',
            'stem borer' => 'Sâu đục thân',
            'leaf folder' => 'Sâu cuốn lá',
            'leaf roller' => 'Sâu cuốn lá',
            'armyworm' => 'Sâu keo',
            'fall armyworm' => 'Sâu keo mùa thu',
            'bollworm' => 'Sâu đục quả',
            'aphid' => 'Rệp mềm',
            'whitefly' => 'Bọ phấn trắng',
            'thrips' => 'Bọ trĩ',
            'beetle' => 'Bọ cánh cứng',
            'weevil' => 'Mọt',
            'grasshopper' => 'Châu chấu',
            'locust' => 'Cào cào',
            'stink bug' => 'Bọ xít',
            'mealybug' => 'Rệp sáp',
            'mite' => 'Nhện đỏ',
            'snail' => 'Ốc sên',
            'slug' => 'Sên trần',
            'rice hispa' => 'Bọ cánh cứng hại lúa',
            'rice bug' => 'Bọ xít hại lúa',
            'fruit fly' => 'Ruồi đục quả',
            'diamondback moth' => 'Sâu tơ',
            'cutworm' => 'Sâu xám',
            'wireworm' => 'Sâu kim',
            'termite' => 'Mối',
            'unknown' => 'Côn trùng chưa xác định'
        );

        if (isset($exact_map[$key])) {
            return $exact_map[$key];
        }

        $contains_map = array(
            'planthopper' => 'Rầy hại lúa',
            'leafhopper' => 'Rầy xanh',
            'stem borer' => 'Sâu đục thân',
            'leaf folder' => 'Sâu cuốn lá',
            'leaf roller' => 'Sâu cuốn lá',
            'armyworm' => 'Sâu keo',
            'bollworm' => 'Sâu đục quả',
            'aphid' => 'Rệp mềm',
            'whitefly' => 'Bọ phấn trắng',
            'thrip' => 'Bọ trĩ',
            'beetle' => 'Bọ cánh cứng',
            'weevil' => 'Mọt',
            'grasshopper' => 'Châu chấu',
            'locust' => 'Cào cào',
            'stink bug' => 'Bọ xít',
            'mealybug' => 'Rệp sáp',
            'mite' => 'Nhện đỏ',
            'snail' => 'Ốc sên',
            'slug' => 'Sên trần',
            'rice bug' => 'Bọ xít hại lúa',
            'fruit fly' => 'Ruồi đục quả',
            'diamondback' => 'Sâu tơ',
            'cutworm' => 'Sâu xám',
            'wireworm' => 'Sâu kim',
            'termite' => 'Mối'
        );

        foreach ($contains_map as $pattern => $translated) {
            if (strpos($key, $pattern) !== false) {
                return $translated;
            }
        }

        // Fall back to a readable label when we do not have a mapping yet.
        $clean = ucwords($key);
        return $clean !== '' ? $clean : 'Côn trùng chưa xác định';
    }
}
