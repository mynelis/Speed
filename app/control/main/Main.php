<?php

namespace app\control\main;

use \Speed\Security\InputValidator;
use \Speed\Templater\Layout;
use \Speed\DataLibrary\Model;
use \Speed\DataLibrary\Binding;

class Main extends \app\control\BaseControl
{
	public function index ()
	{
		// if (!session('app.user.id')) redirect('student/login');
		// redirect('todo/list');


		

		/*$user = Model::create('sys_access sac')
			->select_none()

			->left_join_sys_users('usr','id', 'sac.user_id')
				->select('id, username, fullname, email')
				->username_is('admin')
				// ->password_is('test')
				->active_is(1)

			->left_join_sys_groups('sgr', 'id', 'sac.group_id')
				->select('bind, cms_access, app_access')

			->get(true);

		dump($user, true);*/

		// Layout::set_meta('title', 'test');

		// ifnpost ('csrf_token', function () {
		// 	Layout::register('login_form', [
		// 		(object) [
		// 			'csrf_token' => \Speed\Security\TokenFactory::GetToken('login')
		// 		]
		// 	]);			
		// });

		// dump(Layout::$map, true);

        /*$staff = Model::create('staff');

        post([
        	'fullname' => 'nelis Blanco',
        	'salary' => 9500,
        	'category_id' => 6,
        	'type_id' => 2
        ]);

        $staff = $staff->from_post([
            'fullname' => InputValidator::WORDS,
            'salary' => InputValidator::DECIMAL,
            'category_id' => InputValidator::NUMBER,
            'type_id' => InputValidator::NUMBER
        ]);

        $staff->save();*/

        // dump($staff, true);
        // dump($staff->validation_errors, true);



		// dump(session_expired(), true);

		/*post([
			'fullname' => 'Nelis Blanco',
			'category_id' => 6,
			'type_id' => 2,
			// 'salary' => 8600
		]);

		$staff = Model::create('staff', $id);

        $staff->validation = [
            'fullname' => InputValidator::WORDS,
            'salary' => InputValidator::DECIMAL,
            'category_id' => InputValidator::NUMBER,
            'type_id' => InputValidator::EMAIL
        ];
        
        // $_staff = $staff->get_row('fullname');

        $update = $staff->from_post()->save();
        // if (is_string($update)) return $update;
        // $update = $staff->validate(post());

        // dump($update, true);
        dump($staff->validation_errors, true);*/


		/*$username = 'admin';
		$password = md5('admin');

		$user = Model::create('sys_access sac')
			->select_none()

			->left_join_sys_users('usr', 'sac.user_id', 'usr.id')
				->select('id, username, fullname, email')
				->username_is($username)
				->password_is($password)
				->active_is(1)

			->left_join_sys_groups('sgr', 'sac.group_id', 'sgr.id')
				->select('bind, cms_access, app_access')

			->get(true);

		dump($user, true);*/


		/*$assets = Model::create('vw_assets ass')
			->select('id, coc')
			->custom_select('sum(exp) total_cost')
			// ->limit(2)

			->join_asset_type('type', 'asset_type_id', 'type.id')
				->select('name, code, lifespan')

			->right_join_vw_depreciation('dep', 'dep.aid', 'ass.id')
				->count('dep.aid', 'num_rows')
				->group('fmt')
				->order(['fmt', 'cost' => 'desc'])

			->get();

		dump($assets, true);*/

		

		/*$votes = Model::create('poll_vote vote')
			->select('poll_option_id, user_id uid, id')
			->order('id', 'desc')
			->limit(1)
			
			->join_users('vote.user_id', 'users.id')
				->select('username un, fullname fn')

			->with_users()
				->select('id, fullname fname, email')
				->fullname_contains('Adm')
				->active_is(1)
				->username_is('admin')
				->order('username')
				->order('fullname', 'desc')
				->limit(3)

				->join_access('acc', 'acc.user_id', 'users.id')
					// ->select('acc.group_id gid, fullname fname, email, count(users.id) total')
					->select('acc.group_id gid, fullname fname, email')

			->with_poll_option()
				->select('poll_option.id poid, option opt, poll_id')

				->with_poll_option_with_users()
					->select('username, fullname, email')

				->with_poll_option_with_poll()
					->select('id, subject, created_by')

					->with_poll_option_with_poll_with_users()
						->select('username')

			->get()
			;

		dump($votes, true);*/
		// dump(session('cms')->get());

		// dump(cookie('u'), true);
		// dump(new \app\cache\SchemaData('staff'), true);

		// setcookie('u', session('app.user.id'), null, '/');


		/*Layout::add_scripts([
			'custom', 'rypp'
		]);*/

		/*Layout::register('polls', function () {
			return Model::create('poll')->get();
		});*/

		/*Layout::register('app_domain', function () {
			return $this->app_domain();
		});*/



		/*Layout::register('users_list', function () {
			return Model::create('sys_users')
				->where([
					'active' => 1
				])
				->order('username')
				->limit(2, 0)
				->get();
		});*/

		/*Layout::register('poll_options', function () {
			return Model::create('poll_option')->get();
		});*/
	}
}