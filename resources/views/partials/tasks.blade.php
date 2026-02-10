<div id="tasks-container" class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">Tasks</h2>

    <div>
        <h3 class="font-medium">Todo</h3>
        <ul id="todo-list" class="space-y-2 mt-2">
            @foreach($tasks as $task)
                <li class="flex items-start">
                    <input data-id="{{ $task->id }}" type="checkbox" class="mt-1 mr-3 task-checkbox">
                    <span class="text-gray-700">{{ $task->title }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-4">
        <h3 class="font-medium">最近完了</h3>
        <ul id="done-list" class="mt-2 space-y-1 text-sm text-gray-500">
            @foreach($doneRecent as $d)
                <li>{{ $d->title }}</li>
            @endforeach
        </ul>
    </div>
</div>
