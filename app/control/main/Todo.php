<?php

namespace app\control\main;

use \Speed\Security\InputValidator;
use \Speed\Templater\Layout;
use \Speed\DataLibrary\Model;
use \Speed\DataLibrary\Binding;
use \Speed\Util\Form;

class Todo extends \app\control\BaseControl
{
	public function list ()
	{		
		$this->set_title('Todo: List')

			->set_data('task_list_pending', function () {
				return Model::create('task')
					->status_is(0)
					->order('heading')
					->get(true);
			})

			->set_data('task_list_completed', function () {
				return Model::create('task')
					->status_is(1)
					->order('date_completed', 'desc')
					->order('heading')
					->get(true);
			});
	}

	public function edit ($id)
	{
		// set_view('add');

		// Retrieve the selected item to edit
		$model = Model::create('task', $id);
		$item = $model->get_row();

		$this->set_title($item ? $item->heading : 'Todo: Edit')
			->set_data('category_options', selector_options(Model::create('category')->order('name')->get(), $item ? $item->category_id : 0));


		// Create a Form object and set default data.
		// $form_data is null before form submission, but is an
		// object containing submitted values after submission.
		$form_data = (new Form('todo_edit', Form::REFILL_FROM_FORM))
			->fill($item)
			->collect();

		if ($item && $form_data) {
			// Define form validation rules
			$model->validation = [
				'heading' => InputValidator::WORDS,
				'category_id' => InputValidator::NUMBER
			];

			post('status', 0);

			if ($model->from_post()->save()) {
				redirect('../list');
			}
			else {
				$this->notify((array_values($model->validation_errors)[0]));
			}
		}
	}

	public function add ()
	{
		$this->set_title('Todo: New');

		// Create a new Form object to collect posted form
		$form_data = (new Form('todo_add', Form::REFILL_FROM_FORM))
			->collect();
			
		$categories = Model::create('category')
			->order('name')
			->get();

		$category_options = selector_options($categories, $form_data ? $form_data->category_id : 0);

		$this->set_data('task_categories', $categories)
			->set_data('category_options', $category_options);

		if ($form_data) {
			// Create a model for the new item to be added
			$model = Model::create('task');

			// Set validation rules
			$model->validation = [
				'heading' => InputValidator::WORDS,
				'category_id' => InputValidator::NUMBER
			];

			post('bind', 'entries.new_task');
			
			if ($model->from_post()->save()) {
				redirect('list');
			}
			else {
				$this->notify((array_values($model->validation_errors)[0]));
			}
		}
	}

	public function delete ($id)
	{
		$model = Model::create('task', $id);

		if ($model) {
			$model->delete();
			redirect('../list');
		}
	}

	public function done ($id)
	{
		$model = Model::create('task', $id);

		if ($model) {
			$model->status = 1;
			$model->bind = 'entries.completed_task';

			if ($model->save()) {
				redirect('../list');
			}
			else {
				$this->notify((array_values($model->validation_errors)[0]));
			}
		}
	}
}