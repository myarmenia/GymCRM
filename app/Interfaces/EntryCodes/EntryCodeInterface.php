<?php

namespace App\Interfaces\EntryCodes;

interface EntryCodeInterface
{
    public function getAll();
    public function paginate(int $perPage = 10);
    public function find(int $id);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id);
    public function getByGymId(int $gymId, ?int $currentId = null);
    public function activate(int $entryCodeId, bool $active = true): void;

}