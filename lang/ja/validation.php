<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeは有効なURLで入力してください。',
    'after' => ':attributeは:date以降の日付で入力してください。',
    'after_or_equal' => ':attributeは:date以降の日付で入力してください。',
    'alpha' => ':attributeは英字のみで入力してください。',
    'alpha_dash' => ':attributeは英数字・ハイフン・アンダースコアのみで入力してください。',
    'alpha_num' => ':attributeは英数字のみで入力してください。',
    'array' => ':attributeは配列で指定してください。',
    'ascii' => ':attributeは半角英数字と記号のみで入力してください。',
    'before' => ':attributeは:date以前の日付で入力してください。',
    'before_or_equal' => ':attributeは:date以前の日付で入力してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個の間で指定してください。',
        'file' => ':attributeは:min KBから:max KBの間のファイルを指定してください。',
        'numeric' => ':attributeは:minから:maxの間で入力してください。',
        'string' => ':attributeは:min文字から:max文字の間で入力してください。',
    ],
    'boolean' => ':attributeはtrueまたはfalseで指定してください。',
    'can' => ':attributeに許可されていない値が含まれています。',
    'confirmed' => ':attributeが確認用の入力と一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeは有効な日付で入力してください。',
    'date_equals' => ':attributeは:dateと同じ日付で入力してください。',
    'date_format' => ':attributeは:format形式で入力してください。',
    'decimal' => ':attributeは小数点以下:decimal桁で入力してください。',
    'declined' => ':attributeは拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeは拒否してください。',
    'different' => ':attributeと:otherは異なる値を入力してください。',
    'digits' => ':attributeは:digits桁の数字で入力してください。',
    'digits_between' => ':attributeは:min桁から:max桁の数字で入力してください。',
    'dimensions' => ':attributeの画像サイズが正しくありません。',
    'distinct' => ':attributeに重複した値があります。',
    'doesnt_end_with' => ':attributeは次のいずれかで終わらない値を入力してください。:values',
    'doesnt_start_with' => ':attributeは次のいずれかで始まらない値を入力してください。:values',
    'email' => ':attributeは有効なメールアドレス形式で入力してください。',
    'ends_with' => ':attributeは次のいずれかで終わる値を入力してください。:values',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'extensions' => ':attributeの拡張子は次のいずれかにしてください。:values',
    'file' => ':attributeはファイルを指定してください。',
    'filled' => ':attributeは必須です。',
    'gt' => [
        'array' => ':attributeは:value個より多く指定してください。',
        'file' => ':attributeは:value KBより大きいファイルを指定してください。',
        'numeric' => ':attributeは:valueより大きい値を入力してください。',
        'string' => ':attributeは:value文字より多く入力してください。',
    ],
    'gte' => [
        'array' => ':attributeは:value個以上指定してください。',
        'file' => ':attributeは:value KB以上のファイルを指定してください。',
        'numeric' => ':attributeは:value以上の値を入力してください。',
        'string' => ':attributeは:value文字以上で入力してください。',
    ],
    'hex_color' => ':attributeは有効な16進数カラーコードで入力してください。',
    'image' => ':attributeは画像ファイルを指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeは:otherに存在する値を指定してください。',
    'integer' => ':attributeは整数で入力してください。',
    'ip' => ':attributeは有効なIPアドレスで入力してください。',
    'ipv4' => ':attributeは有効なIPv4アドレスで入力してください。',
    'ipv6' => ':attributeは有効なIPv6アドレスで入力してください。',
    'json' => ':attributeは有効なJSON文字列で入力してください。',
    'lowercase' => ':attributeは小文字で入力してください。',
    'lt' => [
        'array' => ':attributeは:value個より少なく指定してください。',
        'file' => ':attributeは:value KBより小さいファイルを指定してください。',
        'numeric' => ':attributeは:valueより小さい値を入力してください。',
        'string' => ':attributeは:value文字より少なく入力してください。',
    ],
    'lte' => [
        'array' => ':attributeは:value個以下で指定してください。',
        'file' => ':attributeは:value KB以下のファイルを指定してください。',
        'numeric' => ':attributeは:value以下の値を入力してください。',
        'string' => ':attributeは:value文字以下で入力してください。',
    ],
    'mac_address' => ':attributeは有効なMACアドレスで入力してください。',
    'max' => [
        'array' => ':attributeは:max個以下で指定してください。',
        'file' => ':attributeは:max KB以下のファイルを指定してください。',
        'numeric' => ':attributeは:max以下の値を入力してください。',
        'string' => ':attributeは:max文字以内で入力してください。',
    ],
    'max_digits' => ':attributeは:max桁以内の数字で入力してください。',
    'mimes' => ':attributeは:values形式のファイルを指定してください。',
    'mimetypes' => ':attributeは:values形式のファイルを指定してください。',
    'min' => [
        'array' => ':attributeは:min個以上指定してください。',
        'file' => ':attributeは:min KB以上のファイルを指定してください。',
        'numeric' => ':attributeは:min以上の値を入力してください。',
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'min_digits' => ':attributeは:min桁以上の数字で入力してください。',
    'missing' => ':attributeは指定しないでください。',
    'missing_if' => ':otherが:valueの場合、:attributeは指定しないでください。',
    'missing_unless' => ':otherが:valueでない場合、:attributeは指定しないでください。',
    'missing_with' => ':valuesが指定されている場合、:attributeは指定しないでください。',
    'missing_with_all' => ':valuesがすべて指定されている場合、:attributeは指定しないでください。',
    'multiple_of' => ':attributeは:valueの倍数で入力してください。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeは数値で入力してください。',
    'password' => [
        'letters' => ':attributeは英字を1文字以上含めてください。',
        'mixed' => ':attributeは英大文字と英小文字をそれぞれ1文字以上含めてください。',
        'numbers' => ':attributeは数字を1文字以上含めてください。',
        'symbols' => ':attributeは記号を1文字以上含めてください。',
        'uncompromised' => '入力された:attributeは情報漏えいが確認されています。別の:attributeを入力してください。',
    ],
    'present' => ':attributeを指定してください。',
    'present_if' => ':otherが:valueの場合、:attributeを指定してください。',
    'present_unless' => ':otherが:valuesでない場合、:attributeを指定してください。',
    'present_with' => ':valuesが指定されている場合、:attributeを指定してください。',
    'present_with_all' => ':valuesがすべて指定されている場合、:attributeを指定してください。',
    'prohibited' => ':attributeは指定できません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは指定できません。',
    'prohibited_unless' => ':otherが:valuesでない場合、:attributeは指定できません。',
    'prohibits' => ':attributeと:otherは同時に指定できません。',
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeは必須です。',
    'required_array_keys' => ':attributeには次の項目を含めてください。:values',
    'required_if' => ':otherが:valueの場合、:attributeは必須です。',
    'required_if_accepted' => ':otherを承認した場合、:attributeは必須です。',
    'required_unless' => ':otherが:valuesでない場合、:attributeは必須です。',
    'required_with' => ':valuesが指定されている場合、:attributeは必須です。',
    'required_with_all' => ':valuesがすべて指定されている場合、:attributeは必須です。',
    'required_without' => ':valuesが指定されていない場合、:attributeは必須です。',
    'required_without_all' => ':valuesがすべて指定されていない場合、:attributeは必須です。',
    'same' => ':attributeと:otherが一致しません。',
    'size' => [
        'array' => ':attributeは:size個で指定してください。',
        'file' => ':attributeは:size KBのファイルを指定してください。',
        'numeric' => ':attributeは:sizeで入力してください。',
        'string' => ':attributeは:size文字で入力してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まる値を入力してください。:values',
    'string' => ':attributeは文字列で入力してください。',
    'timezone' => ':attributeは有効なタイムゾーンで入力してください。',
    'unique' => '入力された:attributeは既に使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で入力してください。',
    'url' => ':attributeは有効なURL形式で入力してください。',
    'ulid' => ':attributeは有効なULIDで入力してください。',
    'uuid' => ':attributeは有効なUUIDで入力してください。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード確認',
        'title' => 'タイトル',
        'author' => '著者',
        'isbn' => 'ISBN',
        'published_date' => '出版日',
        'description' => '概要',
        'image_url' => '画像URL',
        'genres' => 'ジャンル',
        'genres.*' => 'ジャンル',
        'rating' => '評価',
        'comment' => 'コメント',
        'keyword' => 'キーワード',
        'genre_id' => 'ジャンルID',
        'page' => 'ページ',
        'per_page' => '1ページあたりの件数',
        'user_id' => '登録者ID',
        'book_id' => '書籍',
        'target_date' => '読了予定日',
    ],

];
