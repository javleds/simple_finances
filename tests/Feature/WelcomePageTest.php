<?php

it('serves the api welcome page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('fin-si API')
        ->assertSee('Service is available.');
});
