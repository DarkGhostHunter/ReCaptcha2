<?php
/**
 * This is a PHP library that handles calling reCAPTCHA.
 *
 * BSD 3-Clause License
 *
 * @copyright (c) 2019, Google Inc.
 * @link https://www.google.com/recaptcha
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its
 *    contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Google\ReCaptcha;

use Serializable;
use JsonSerializable;

/**
 * Class ReCaptchaResponse
 *
 * @package Google\ReCaptcha\Http
 *
 * @property-read null|string $hostname
 * @property-read null|string $challenge_ts
 * @property-read null|string $apk_package_name
 * @property-read null|float $score
 * @property-read null|string $action
 * @property-read array $error_codes
 * @property-read bool $success
 */
class ReCaptchaResponse implements JsonSerializable, Serializable
{
    /**
     * ReCaptchaResponse attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Additional constraints to check after the response is received.
     *
     * @var array
     */
    protected $constraints = ReCaptcha::CONSTRAINTS_ARRAY;

    /**
     * ReCaptchaResponse constructor.
     *
     * @param  array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Returns all the response attributes as an array
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Returns if the challenge is valid.
     *
     * @return bool
     */
    public function valid()
    {
        return ($this->attributes['success'] ?? false) && $this->error_codes === [];
    }

    /**
     * Returns if the challenge is invalid.
     *
     * @return bool
     */
    public function invalid()
    {
        return ! $this->valid();
    }

    /**
     * Return if the response has a particular error
     *
     * @param  mixed ...$errors
     * @return bool
     */
    public function hasError(...$errors)
    {
        if (empty($errors)) {
            return false;
        }

        $array = $this->error_codes ?? [];

        foreach ($errors as $error) {
            if (! in_array($error, $array, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add ann array of errors to the existing error codes.
     *
     * @param  array $errors
     * @return \Google\ReCaptcha\ReCaptchaResponse
     */
    public function addErrors(array $errors = [])
    {
        $this->attributes['error-codes'] = array_merge($this->attributes['error-codes'] ?? [], $errors);

        return $this;
    }

    /**
     * Return a single constraint value.
     *
     * @param  string $name
     * @return mixed
     */
    public function constraint(string $name)
    {
        return $this->constraints[$name];
    }

    /**
     * Returns the constraints to check this response.
     *
     * @return array
     */
    public function constraints()
    {
        return $this->constraints;
    }

    /**
     * Sets the constraints to check this response.
     *
     * @param  array $constraints
     * @return $this
     */
    public function setConstraints(array $constraints)
    {
        $this->constraints = $constraints;

        return $this;
    }

    /**
     * Dynamically return an attribute as a property.
     *
     * @param $name
     * @return null|mixed
     */
    public function __get($name)
    {
        // Minor fix for getting the error codes
        if ($name === 'error_codes') {
            return $this->attributes['error-codes'];
        }

        return $this->attributes[$name];
    }

    /**
     * Returns an array representation of the ReCaptchaResponse.
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge($this->attributes, [
            'constraints' => $this->constraints,
        ]);
    }

    /**
     * Returns a string representation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @see https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Returns a JSON representation of the object
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation of object
     *
     * @see https://php.net/manual/en/serializable.serialize.php
     * @return string
     */
    public function serialize()
    {
        return $this->toJson();
    }

    /**
     * Constructs the object
     *
     * @see https://php.net/manual/en/serializable.unserialize.php
     * @param  string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        $array = json_decode($serialized, true);

        $this->constraints = $array['constraints'];

        if (isset($array['score'])) {
            $array['score'] = (float)$array['score'];
        }

        unset($array['constraints']);

        $this->attributes = $array;
    }
}
