<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages.
    |
    */


    /**
     * Custom Validation Messages
     */


    'field_required' => "this field is required",

    'accepted'             => 'must accept :attribute',
    'active_url'           => ':attribute it doesnot represent a right url',
    'after'                => 'date must be :attribute subsequent to the date :date.',
    'after_or_equal'       => ':attribute the date must be subsequent to or matched with the date :date.',
    'alpha'                => 'it mustnot contain :attribute only letters',
    'alpha_dash'           => 'it mustnot contain :attribute letters,numbers and ومطّات.',
    'alpha_num'            => 'it must contain :attribute only letters and numbers',
    'array'                => 'it must be :attribute matrix',
    'before'               => 'date must :attribute be preceding to the date :date.',
    'before_or_equal'      => ':attribute date must be preceding to or matched with the date :date',
    'between'              => [
        'numeric' => 'it must be a value :attribute between :min و :max.',
        'file'    => 'file size must be :attribute between :min و :max kilobyte.',
        'string'  => 'text letters number must be :attribute between :min و :max',
        'array'   => 'it must contain :attribute number of elements :min و :max',
    ],
    'boolean'              => 'it must be a value :attribute either true or false ',
    'confirmed'            => 'confirmation field doesnot match with the field :attribute',
    'date'                 => ':attribute it isnot a right date',
    'date_format'          => 'doesnot match :attribute with the format :format.',
    'different'            => 'the two fields must be :attribute and :other different',
    'digits'               => 'it must contain :attribute  :digits number/numbers',
    'digits_between'       => 'it must contain :attribute between :min and :max number/numbers ',
    'dimensions'           => 'الـ :attribute contains invalid photo dimensions.',
    'distinct'             => 'the field has :attribute a repeated value.',
    'email'                => 'it must be :attribute a right e-mail',
    'exists'               => 'the specific value :attribute غير موجودة',
    'file'                 => 'الـ :attribute it must be a file.',
    'filled'               => ':attribute obligatory',
    'image'                => 'it must be :attribute an image',
    'in'                   => ':attribute null',
    'in_array'             => ':attribute doesnot exist :other.',
    'integer'              => 'it must be :attribute a right number',
    'ip'                   => 'it must be :attribute address IP right',
    'ipv4'                 => 'it must be :attribute address IPv4 right.',
    'ipv6'                 => 'it must be :attribute address IPv6 right.',
    'json'                 => 'the text type  :attribute must be JSON.',
    'max'                  => [
        'numeric' => 'value must be :attribute equal to or less than :max.',
        'file'    => 'file size mustnot exceed :attribute :max kilo byte',
        'string'  => 'text length mustnot exceed :attribute :max letter/letters',
        'array'   => 'it mustnot contain :attribute more than :max element/elements.',
    ],
    'mimes'                => 'file type must be : :values.',
    'mimetypes'            => 'file type must be : :values.',
    'min'                  => [
        'numeric' => 'value must be :attribute equal to or exceeds :min.',
        'file'    => 'file size must be :attributeat least :min kilo byte',
        'string'  => 'text length must be :attribute at least :min letter/letters',
        'array'   => 'it must contain :attribute at least :min element/elements',
    ],
    'not_in'               => ':attribute null',
    'numeric'              => 'it must :attribute be a number',
    'present'              => 'must present :attribute',
    'regex'                => 'formula :attribute .untrue',
    'required'             => ':attribute required.',
    'required_if'          => ':attribute required if it  :other equals :value.',
    'required_unless'      => ':attribute required if it doesnot :other equal :values.',
    'required_with'        => ':attribute if it is available  :values.',
    'required_with_all'    => ':attribute if it is available  :values.',
    'required_without'     => ':attribute if it isnot available  :values.',
    'required_without_all' => ':attribute required if it isnot available :values.',
    'same'                 => 'it must be matched :attribute with :other',
    'size'                 => [
        'numeric' => 'it must be a value :attribute equal to :size',
        'file'    => 'file size must be :attribute :size kilo byte',
        'string'  => 'text must contain :attribute على :size letter/letters exactly',
        'array'   => 'it must contain :attribute على :size element/elements exactly',
    ],
    'string'               => 'it must be :attribute text.',
    'timezone'             => 'it must be :attribute true time zone',
    'unique'               => ' :attribute used before',
    'uploaded'             => 'failed in uploading the :attribute',
    'url'                  => 'url formula :attribute isnot true',

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

    'custom'               => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes'           => [
        'name'                  => 'name',
        'username'              => 'user name',
        'email'                 => 'e-mail',
        'first_name'            => 'first name',
        'last_name'             => 'اlast name',
        'password'              => 'password',
        'password_confirmation' => 'confirm password',
        'city'                  => 'city',
        'country'               => 'country',
        'address'               => 'address',
        'phone'                 => 'phone',
        'mobile'                => 'mobile',
        'age'                   => 'age',
        'sex'                   => 'sex',
        'gender'                => 'gender',
        'day'                   => 'day',
        'month'                 => 'month',
        'year'                  => 'year',
        'hour'                  => 'hour',
        'minute'                => 'minute',
        'second'                => 'second',
        'title'                 => 'title',
        'content'               => 'content',
        'description'           => 'description',
        'excerpt'               => 'summary',
        'date'                  => 'date',
        'time'                  => 'time',
        'available'             => 'available',
        'size'                  => 'size',
        'activation_code'       => 'activation code',
        'brand_id'       => 'car brand',
        'model_id'       => 'car model',
        'notes'       => 'details',
        'maintenance_id'       => 'maintainance type',
    ],
];
