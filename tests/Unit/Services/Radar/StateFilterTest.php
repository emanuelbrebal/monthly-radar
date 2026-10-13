<?php

use App\Services\Radar\StateFilter;

beforeEach(function () {
    $this->filter = new StateFilter;
});

it('matches everything when the selection is empty', function () {
    expect($this->filter->matches([], ['RN'], false))->toBeTrue();
    expect($this->filter->matches([], [], false))->toBeTrue();
});

it('matches nationwide contests when NACIONAL is selected', function () {
    expect($this->filter->matches(['NACIONAL'], [], true))->toBeTrue();
    expect($this->filter->matches(['NACIONAL'], ['RN'], false))->toBeFalse();
});

it('matches when states intersect the selection', function () {
    expect($this->filter->matches(['RN', 'CE'], ['CE', 'PB'], false))->toBeTrue();
    expect($this->filter->matches(['RN'], ['CE', 'PB'], false))->toBeFalse();
});

it('matches contests with no known location ("local a definir")', function () {
    expect($this->filter->matches(['RN'], [], false))->toBeTrue();
    expect($this->filter->matches(['RN'], [], true))->toBeFalse();
});
