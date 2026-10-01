import test from 'node:test';
import assert from 'node:assert/strict';
import { createChoiceSelection } from '../../resources/js/choice-selection-state.js';
const options = [{id:1},{id:2},{id:3},{id:4}];
test('click add/remove restores original available order', () => {
 const state = createChoiceSelection(options); state.add(3); state.add(1);
 assert.deepEqual(state.selected(), ['3','1']); state.remove(3);
 assert.deepEqual(state.available(), ['2','3','4']); state.remove(1);
 assert.deepEqual(state.available(), ['1','2','3','4']);
});
test('add all keeps current preference and appends remaining original order', () => {
 const state=createChoiceSelection(options);state.add(3);state.addAll();
 assert.deepEqual(state.selected(),['3','1','2','4']);state.addAll();assert.equal(state.selected().length,4);
 state.removeAll();assert.deepEqual(state.selected(),[]);assert.deepEqual(state.available(),['1','2','3','4']);
});
test('drag insert before/after and selected reordering keep unique choices', () => {
 const state=createChoiceSelection(options,['1','2','3']); state.add(4,2); assert.deepEqual(state.selected(),['1','4','2','3']);
 state.add(1,3,true);assert.deepEqual(state.selected(),['4','2','3','1']);state.add(3,3);assert.deepEqual(state.selected(),['4','2','3','1']);
 state.move(1,-1);assert.deepEqual(state.selected(),['4','2','1','3']);state.move(4,-1);assert.deepEqual(state.selected(),['4','2','1','3']);
});
test('restored selections are valid unique IDs and keep reviewed order', () => {
 const state=createChoiceSelection(options,[3,3,99,1]);assert.deepEqual(state.selected(),['3','1']);state.add(99);assert.deepEqual(state.selected(),['3','1']);
});
