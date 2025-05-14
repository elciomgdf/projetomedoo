<?php

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\TaskCategoryModel;
use App\Models\TaskModel;
use App\Validators\TaskCategoryValidator;

class TaskCategoryService extends Service
{

    /**
     * @param int $limit
     * @param string $orderBy
     * @param string|null $table
     * @return array
     */
    public function all(int $limit = 1000, string $orderBy = 'id', string $table = null): array
    {
        $items = (new TaskCategoryModel())->all($limit, $orderBy);
        if (!empty($items)) {
            foreach ($items as $key => $item) {
                $items[$key]['encoded_id'] = $this->encode($item['id']);
            }
        }
        return $items;
    }

    /**
     * @param $data
     * @return array
     */
    public function search($data): array
    {

        $page = max(1, $data['page'] ?? 1);

        $where = [];

        if (!empty($data['q'])) {
            $where = ['name[~]' => "%{$data['q']}%"];
        }

        $fields = (new TaskCategoryModel())->getFields();

        $orderBy = sanitizeOrderBy($data['order'] ?? 'name', $fields);

        $per_page = $data['per_page'] ?? 10;

        $data = (new TaskCategoryModel())->paginate($page, (int)$per_page, $where, $orderBy, $data['direction'] ?? 'ASC');

        if (!empty($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                $data['items'][$key]['encoded_id'] = $this->encode($item['id']);
            }
        }

        return $data;

    }

    /**
     * @param $data
     * @param $id
     * @return TaskCategoryModel|null
     * @throws NotFoundException
     */
    public function save($data, $id = null): ?TaskCategoryModel
    {

        $data = TaskCategoryValidator::validate($data, $id);

        $model = new TaskCategoryModel();
        if ($id) {
            $model->find($id);
            if (empty($model->getId())) {
                throw new NotFoundException("Registro não encontrado");
            }
        }
        $model->fill($data);

        $model->save();

        return $model;

    }

    /**
     * @param int|null $id
     * @param string|null $table
     * @return bool
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function delete(int $id = null, string $table = null): bool
    {

        if (empty($id)) {
            throw new NotFoundException("Registro não encontrado");
        }

        if ($id === 1) {
            throw new ValidationException("A Categoria geral não pode ser excluída");
        }

        if ((new TaskModel())->findOneBy(["category_id" => $id])) {
            throw new \Exception("Não é possível excluir uma categoria que esteja sendo usada");
        };

        $model = new TaskCategoryModel();
        $deleted = $model->delete($id);

        if (empty($deleted)) {
            throw new NotFoundException("Registro não encontrado!");
        }

        return true;

    }

}