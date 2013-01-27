<?php
namespace Validator;

class LIVR {
    private $is_prepared = false;
    private $livr_rules = [];
    private $validators = [];
    private $validator_builders = [];
    private $errors;

    private static $DEFAULT_RULES = [
        'required'       => 'Validator\LIVR\Rules\Common::required',
        'not_empty'      => 'Validator\LIVR\Rules\Common::not_empty',
        
        'one_of'         => 'Validator\LIVR\Rules\String::one_of',
        'min_length'     => 'Validator\LIVR\Rules\String::min_length',
        'max_length'     => 'Validator\LIVR\Rules\String::max_length',
        'length_equal'   => 'Validator\LIVR\Rules\String::length_equal',
        'length_between' => 'Validator\LIVR\Rules\String::length_between',
        'like'           => 'Validator\LIVR\Rules\String::like'
    ];

    
    public static function register_default_rules($rules) {
        self::$DEFAULT_RULES = $rules + self::$DEFAULT_RULES;
        return self;
    }

    public static function get_default_rules() {
        return self::$DEFAULT_RULES;
    }

    public function __construct($livr_rules) {
        $this->livr_rules = $livr_rules;
        $this->register_rules(self::$DEFAULT_RULES); 
    }


    public function prepare() {
        if ( $this->is_prepared ) {
            return;
        }

        $validators = [];
        foreach ( $this->livr_rules as $field => $field_rules ) {
            if ( $this->is_assoc_array($field_rules) ) {
                $field_rules = [$field_rules];
            }

            foreach ($field_rules as $rule) {
                list($name, $args) = $this->parse_rule($rule);

                array_push($validators, $this->build_validator($name, $args));
            }

            $this->validators[$field] = $validators;
        }
            
        $this->is_prepared = true;
    }


    public function validate($data) {
        if ( $this->is_prepared ) {
            $this->prepare();
        }

        if ( ! $this->is_assoc_array($data) ) {
            $this->errors = 'FORMAT_ERROR';
            return;
        }

        $errors = [];
        $result = [];

        foreach ( $this->validators as $field_name => $validators ) {
            if ( count($this->validators) == 0 ) {
                continue;
            }

            $value = $data[$field_name];

            $is_ok = true;
            $field_result;

            foreach ($validators as $v_cb) {
                $field_result = NULL;
                $err_code = $v_cb( $value, $data, $field_result );

                if ( $err_code ) {
                    $errors[$field_name] = $err_code;
                    $is_ok = false;
                    
                    break;
                }
            }

            if ( $is_ok && array_key_exists($field_name, $data) ) {
                $result[$field_name] = isset($field_result) ? $field_result  : $value;
            }
        }


        if ( count($errors) > 0 ) {
            $this->errors = $errors;
            return false;
        } else {
            unset($this->errors);
            return $result;
        }
    }


    public function get_errors() {
        return $this->errors;
    }

    public function register_rules($rules) {
        $this->validator_builders += $rules;
        return $this;
    }

    public function get_rules() {
        return $this->validator_builders;
    }

    private function parse_rule($livr_rule) {
        if ( $this->is_assoc_array($livr_rule) ) {
            reset($livr_rule);
            $name = key($livr_rule);

            $args = $livr_rule[$name];

            if ( !is_array($args) ) {
                $args = [$args];
            }
        } else {
             $name = $livr_rule;
             $args = [];
        }

        return [$name, $args];
    }


    private function build_validator($name, $args) {
        if ( !array_key_exists($name, $this->validator_builders) ) {
            throw new \Exception( "Rule [$name] not registered" );
        }

        $func_args = $args;
        array_push($func_args, $this->validator_builders);

        return call_user_func_array($this->validator_builders[$name], $func_args);
    }


    private function is_assoc_array($arr) {
        if ( ! is_array($arr) ) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}   
